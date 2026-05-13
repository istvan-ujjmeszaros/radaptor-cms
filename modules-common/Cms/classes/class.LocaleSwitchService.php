<?php

declare(strict_types=1);

final class LocaleSwitchService
{
	private const string SESSION_KEY = 'radaptor_locale';
	private const string COOKIE_KEY = 'radaptor_locale';
	private const int ANONYMOUS_LOCALE_COOKIE_LIFETIME_SECONDS = 365 * 86400;

	public const string REDIRECT_REASON_SOURCE_NOT_FOUND = 'source_not_found';
	public const string REDIRECT_REASON_DYNAMIC_RESOURCE = 'dynamic_resource';
	public const string REDIRECT_REASON_MISSING_SITE_CONTEXT = 'missing_site_context';
	public const string REDIRECT_REASON_MISSING_LOCALE_HOME = 'missing_locale_home';
	public const string REDIRECT_REASON_HOME_URL_UNAVAILABLE = 'home_url_unavailable';
	public const string REDIRECT_REASON_LOCALE_HOME = 'locale_home';

	public static function getStoredRequestLocale(): ?string
	{
		$session_locale = LocaleService::tryCanonicalize((string) Request::_SESSION(self::SESSION_KEY, ''));

		if ($session_locale !== null && LocaleService::isEnabled($session_locale)) {
			return $session_locale;
		}

		$cookie_locale = LocaleService::tryCanonicalize(self::getCookieValue());

		return $cookie_locale !== null && LocaleService::isEnabled($cookie_locale) ? $cookie_locale : null;
	}

	public static function persistAnonymousLocale(string $locale): void
	{
		$locale = LocaleService::canonicalize($locale);
		Request::startSession();
		Request::saveSessionData([self::SESSION_KEY], $locale);

		if (!headers_sent()) {
			setcookie(self::COOKIE_KEY, $locale, self::getAnonymousLocaleCookieOptions());
		}

		RequestContextHolder::current()->COOKIE[self::COOKIE_KEY] = $locale;
	}

	/**
	 * @return array{expires: int, path: string, secure: bool, httponly: bool, samesite: string}
	 */
	public static function getAnonymousLocaleCookieOptions(?int $now = null): array
	{
		return [
			'expires' => ($now ?? time()) + self::ANONYMOUS_LOCALE_COOKIE_LIFETIME_SECONDS,
			'path' => '/',
			'secure' => self::isSecureRequest(),
			'httponly' => true,
			'samesite' => 'Lax',
		];
	}

	public static function isSameOriginPostRequest(): bool
	{
		if (Request::getMethod() !== 'POST') {
			return false;
		}

		// Reverse proxies must pass the public scheme/host/port into the runtime
		// server context, otherwise the browser Origin cannot match safely.
		$server = self::getServerContext();
		$origin = trim(self::getServerValue($server, 'HTTP_ORIGIN'));

		if ($origin !== '') {
			return strtolower($origin) !== 'null' && self::isSameOriginUrl($origin);
		}

		$referer = trim(self::getServerValue($server, 'HTTP_REFERER'));

		return $referer !== '' && self::isSameOriginUrl($referer);
	}

	/**
	 * @return array{url: string, reason: string}
	 */
	public static function resolveRedirectDecisionForLocale(string $return_url, string $locale): array
	{
		$return_url = self::sanitizeSameSiteReturnUrl($return_url);
		$locale = LocaleService::canonicalize($locale);
		$resource = self::resolveResourceFromUrl($return_url);

		if (!is_array($resource)) {
			return self::redirectDecision($return_url, self::REDIRECT_REASON_SOURCE_NOT_FOUND);
		}

		$resource_id = (int) ($resource['node_id'] ?? 0);

		if ($resource_id <= 0 || ResourceLocaleService::getInheritedContentLocale($resource_id) === null) {
			return self::redirectDecision($return_url, self::REDIRECT_REASON_DYNAMIC_RESOURCE);
		}

		$site_context = ResourceLocaleService::getSiteContextForResourceId($resource_id);

		if ($site_context === null) {
			return self::redirectDecision($return_url, self::REDIRECT_REASON_MISSING_SITE_CONTEXT);
		}

		$home_id = LocaleHomeResourceService::getEffectiveHomeResourceId($site_context, $locale);

		if ($home_id === null) {
			return self::redirectDecision($return_url, self::REDIRECT_REASON_MISSING_LOCALE_HOME);
		}

		$home_url = Url::getSeoUrl($home_id);

		if ($home_url === null) {
			return self::redirectDecision($return_url, self::REDIRECT_REASON_HOME_URL_UNAVAILABLE);
		}

		return self::redirectDecision($home_url, self::REDIRECT_REASON_LOCALE_HOME);
	}

	public static function resolveRedirectUrlForLocale(string $return_url, string $locale): string
	{
		return self::resolveRedirectDecisionForLocale($return_url, $locale)['url'];
	}

	public static function sanitizeSameSiteReturnUrl(string $url): string
	{
		$url = Url::sanitizeRefererUrl($url);

		if ($url === '') {
			return Url::getCurrentHost();
		}

		$parsed = parse_url($url);

		if ($parsed === false) {
			return Url::getCurrentHost();
		}

		if (isset($parsed['scheme']) && !in_array(strtolower((string) $parsed['scheme']), ['http', 'https'], true)) {
			return Url::getCurrentHost();
		}

		if (!isset($parsed['host'])) {
			return str_starts_with($url, '/') && !str_starts_with($url, '//')
				? $url
				: Url::getCurrentHost();
		}

		if (!isset($parsed['scheme'])) {
			return Url::getCurrentHost();
		}

		$current = parse_url(Url::getCurrentHost(false));

		if (
			is_array($current)
			&& strcasecmp((string) $parsed['scheme'], (string) ($current['scheme'] ?? '')) === 0
			&& strcasecmp((string) $parsed['host'], (string) ($current['host'] ?? '')) === 0
			&& self::effectivePort($parsed) === self::effectivePort($current)
		) {
			return $url;
		}

		return Url::getCurrentHost();
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function resolveResourceFromUrl(string $url): ?array
	{
		$parsed = parse_url($url);

		if ($parsed === false) {
			return null;
		}

		$query = [];

		if (isset($parsed['query'])) {
			parse_str((string) $parsed['query'], $query);
		}

		if (isset($query['folder'], $query['resource'])) {
			$lookup = self::normalizeResourceLookupParts((string) $query['folder'], (string) $query['resource']);
		} else {
			$path = (string) ($parsed['path'] ?? '/');
			$lookup = self::normalizeResourceLookupPath($path);
		}

		if ($lookup === null) {
			return null;
		}

		$row = ResourceTreeHandler::getResourceTreeEntryData($lookup['folder'], $lookup['resource_name']);

		return is_array($row) ? $row : null;
	}

	/**
	 * @return array{folder: string, resource_name: string}|null
	 */
	private static function normalizeResourceLookupParts(string $folder, string $resource_name): ?array
	{
		$resource_name = trim($resource_name);

		if ($resource_name === '' || str_contains($resource_name, '/') || str_contains($resource_name, '\\')) {
			return null;
		}

		return self::normalizeResourceLookupPath(rtrim($folder, '/') . '/' . $resource_name);
	}

	/**
	 * @return array{folder: string, resource_name: string}|null
	 */
	private static function normalizeResourceLookupPath(string $path): ?array
	{
		$path = rawurldecode($path);

		if ($path === '' || $path[0] !== '/' || str_contains($path, '\\')) {
			return null;
		}

		if ($path === '' || str_ends_with($path, '/')) {
			$path .= 'index.html';
		}

		$segments = [];

		foreach (explode('/', $path) as $segment) {
			if ($segment === '') {
				continue;
			}

			if ($segment === '.' || $segment === '..') {
				return null;
			}

			$segments[] = $segment;
		}

		if ($segments === []) {
			$segments[] = 'index.html';
		}

		$resource_name = array_pop($segments);

		if (!is_string($resource_name) || $resource_name === '') {
			return null;
		}

		$folder = $segments === [] ? '/' : '/' . implode('/', $segments) . '/';

		return [
			'folder' => $folder,
			'resource_name' => $resource_name,
		];
	}

	private static function getCookieValue(): string
	{
		try {
			$cookie = RequestContextHolder::current()->COOKIE;
		} catch (Throwable) {
			$cookie = [];
		}

		if ($cookie === [] && $_COOKIE !== []) {
			$cookie = $_COOKIE;
		}

		return (string) ($cookie[self::COOKIE_KEY] ?? '');
	}

	private static function isSecureRequest(): bool
	{
		$server = self::getServerContext();

		return !empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off';
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function redirectDecision(string $url, string $reason): array
	{
		return [
			'url' => $url,
			'reason' => $reason,
		];
	}

	private static function isSameOriginUrl(string $url): bool
	{
		$parsed = parse_url($url);

		if (!is_array($parsed) || !isset($parsed['scheme'], $parsed['host'])) {
			return false;
		}

		if (!in_array(strtolower((string) $parsed['scheme']), ['http', 'https'], true)) {
			return false;
		}

		$current = parse_url(Url::getCurrentHost(false));

		return is_array($current)
			&& strcasecmp((string) $parsed['scheme'], (string) ($current['scheme'] ?? '')) === 0
			&& strcasecmp((string) $parsed['host'], (string) ($current['host'] ?? '')) === 0
			&& self::effectivePort($parsed) === self::effectivePort($current);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function getServerContext(): array
	{
		try {
			$server = RequestContextHolder::current()->SERVER;
		} catch (Throwable) {
			$server = [];
		}

		return $server !== [] ? $server : $_SERVER;
	}

	/**
	 * @param array<string, mixed> $server
	 */
	private static function getServerValue(array $server, string $key): string
	{
		$lower_key = strtolower($key);

		return (string) ($server[$key] ?? $server[$lower_key] ?? '');
	}

	/**
	 * @param array<string, mixed> $parts
	 */
	private static function effectivePort(array $parts): int
	{
		if (isset($parts['port'])) {
			return (int) $parts['port'];
		}

		return strtolower((string) ($parts['scheme'] ?? 'http')) === 'https' ? 443 : 80;
	}
}
