<?php

declare(strict_types=1);

final class LocaleSwitchService
{
	private const string SESSION_KEY = 'radaptor_locale';
	private const string COOKIE_KEY = 'radaptor_locale';

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
			setcookie(self::COOKIE_KEY, $locale, [
				'expires' => time() + 31536000,
				'path' => '/',
				'secure' => self::isSecureRequest(),
				'httponly' => false,
				'samesite' => 'Lax',
			]);
		}

		RequestContextHolder::current()->COOKIE[self::COOKIE_KEY] = $locale;
	}

	public static function resolveRedirectUrlForLocale(string $return_url, string $locale): string
	{
		$return_url = self::sanitizeSameSiteReturnUrl($return_url);
		$locale = LocaleService::canonicalize($locale);
		$resource = self::resolveResourceFromUrl($return_url);

		if (!is_array($resource)) {
			return $return_url;
		}

		$resource_id = (int) ($resource['node_id'] ?? 0);

		if ($resource_id <= 0 || ResourceLocaleService::getInheritedContentLocale($resource_id) === null) {
			return $return_url;
		}

		$site_context = ResourceLocaleService::getSiteContextForResourceId($resource_id);

		if ($site_context === null) {
			return Url::getCurrentHost();
		}

		$home_id = LocaleHomeResourceService::getEffectiveHomeResourceId($site_context, $locale);

		if ($home_id === null) {
			return Url::getCurrentHost();
		}

		return Url::getSeoUrl($home_id) ?? Url::getCurrentHost();
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
			$folder = (string) $query['folder'];
			$resource_name = (string) $query['resource'];
		} else {
			$path = (string) ($parsed['path'] ?? '/');

			if ($path === '' || str_ends_with($path, '/')) {
				$path .= 'index.html';
			}

			$info = pathinfo($path);
			$folder = '/' . trim((string) ($info['dirname'] ?? '/'), '/') . '/';
			$folder = $folder === '//' ? '/' : $folder;
			$resource_name = (string) ($info['basename'] ?? 'index.html');
		}

		$row = ResourceTreeHandler::getResourceTreeEntryData($folder, $resource_name);

		return is_array($row) ? $row : null;
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
		$server = RequestContextHolder::current()->SERVER;

		return !empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off';
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
