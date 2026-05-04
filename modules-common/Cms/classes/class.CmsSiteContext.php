<?php

final class CmsSiteContext
{
	public const string DEFAULT_SITE_KEY = 'app';

	/**
	 * Resolve the logical CMS site context for the current request.
	 *
	 * Host names are treated as aliases, not as the content tree identity. For
	 * legacy installs, an existing host-named root is still accepted. Single-site
	 * databases fall back to their only non-empty root so local hosts can serve
	 * migrated content without renaming the root to the current host.
	 *
	 * The fallback rules below are intentionally limited to unambiguous legacy
	 * shapes: one populated root, one host-named root, or one root total. Multiple
	 * populated roots without an explicit host alias mapping are rejected instead
	 * of guessed. Hostless multi-site contexts are accepted only when the configured
	 * site key names an existing populated root.
	 */
	public static function resolve(?string $explicit_context = null): string
	{
		$explicit_context = self::normalizeSiteKey($explicit_context);

		if ($explicit_context !== '') {
			return $explicit_context;
		}

		$env_context = self::normalizeSiteKey((string) getenv('RADAPTOR_SITE_CONTEXT'));

		if ($env_context !== '') {
			return self::resolveHostlessConfiguredContext($env_context);
		}

		$host = self::getCurrentHostKey();
		$aliases = self::getHostAliases();

		if ($host !== '' && isset($aliases[$host])) {
			return $aliases[$host];
		}

		$configured = self::getConfiguredSiteKey();
		$configured_root = $configured !== '' ? self::getRootByName($configured) : null;
		$content_roots = self::getContentRootRows();

		if (count($content_roots) > 1) {
			if (self::hasExplicitHostAliasConfig() && $host === '') {
				if (is_array($configured_root) && self::rootHasChildren((int) $configured_root['node_id'])) {
					return $configured;
				}

				throw self::hostlessSiteContextException($configured, $content_roots);
			}

			if (self::hasExplicitHostAliasConfig()) {
				throw self::unmappedHostException($host);
			}

			throw self::ambiguousSiteRootsException($content_roots);
		}

		if (is_array($configured_root) && self::rootHasChildren((int) $configured_root['node_id'])) {
			return $configured;
		}

		$content_root = self::getSingleContentRootName($content_roots);

		if ($content_root !== null) {
			return $content_root;
		}

		if (is_array($configured_root)) {
			return $configured;
		}

		$host_root = $host !== '' ? self::getRootByName($host) : null;

		if (is_array($host_root) && self::rootHasChildren((int) $host_root['node_id'])) {
			return $host;
		}

		if (is_array($host_root)) {
			return $host;
		}

		$single_root = self::getSingleRootName();

		if ($single_root !== null) {
			return $single_root;
		}

		return $configured !== '' ? $configured : self::DEFAULT_SITE_KEY;
	}

	public static function getConfiguredSiteKey(): string
	{
		$site_context = self::getConfigString('APP_SITE_CONTEXT', '');

		if ($site_context !== '') {
			return $site_context;
		}

		$legacy_domain_context = self::getConfigString('APP_DOMAIN_CONTEXT', '');

		if ($legacy_domain_context !== '') {
			return $legacy_domain_context;
		}

		return self::DEFAULT_SITE_KEY;
	}

	public static function getCurrentRootId(?string $site_context = null): ?int
	{
		$root = self::getRootByName(self::resolve($site_context));

		return is_array($root) ? (int) $root['node_id'] : null;
	}

	public static function getCurrentHostKey(): string
	{
		try {
			$server = RequestContextHolder::current()->SERVER;
		} catch (Throwable) {
			$server = [];
		}

		if ($server === []) {
			$server = $_SERVER;
		}

		$host = (string) ($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? '');

		return self::normalizeHost($host);
	}

	/**
	 * @return array<string, string>
	 */
	public static function getHostAliases(): array
	{
		return self::normalizeHostAliasConfig()['aliases'];
	}

	public static function hasExplicitHostAliasConfig(): bool
	{
		return self::normalizeHostAliasConfig()['explicit'];
	}

	public static function getPrimaryHostForSite(string $site_key): ?string
	{
		$site_key = self::normalizeSiteKey($site_key);

		if ($site_key === '') {
			return null;
		}

		return self::normalizeHostAliasConfig()['primary_hosts'][$site_key] ?? null;
	}

	/**
	 * @return list<string>
	 */
	public static function getHostsForSite(string $site_key): array
	{
		$site_key = self::normalizeSiteKey($site_key);

		if ($site_key === '') {
			return [];
		}

		$hosts = [];
		$primary = self::getPrimaryHostForSite($site_key);

		if ($primary !== null) {
			$hosts[] = $primary;
		}

		foreach (self::getHostAliases() as $host => $alias_site_key) {
			if ($alias_site_key === $site_key) {
				$hosts[] = $host;
			}
		}

		return array_values(array_unique($hosts));
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function getRootRows(): array
	{
		return DbHelper::selectMany('resource_tree', ['node_type' => 'root']);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function getContentRootRows(): array
	{
		$content_roots = [];

		foreach (self::getRootRows() as $root) {
			if (self::rootHasChildren((int) $root['node_id'])) {
				$content_roots[] = $root;
			}
		}

		return $content_roots;
	}

	public static function rootExists(string $site_key): bool
	{
		return self::getRootByName($site_key) !== null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function getRootByName(string $site_key): ?array
	{
		$site_key = self::normalizeSiteKey($site_key);

		if ($site_key === '') {
			return null;
		}

		$root = DbHelper::selectOne('resource_tree', [
			'node_type' => 'root',
			'resource_name' => $site_key,
		]);

		return is_array($root) ? $root : null;
	}

	/**
	 * @param list<array<string, mixed>> $roots
	 */
	public static function ambiguousSiteRootsException(array $roots): RuntimeException
	{
		$root_names = array_map(
			static fn (array $root): string => (string) ($root['resource_name'] ?? ''),
			$roots
		);
		$root_names = array_values(array_filter($root_names, static fn (string $name): bool => $name !== ''));

		return new RuntimeException(
			'Multiple populated CMS site roots exist without an explicit host alias mapping: '
			. implode(', ', $root_names)
			. ". Configure ApplicationConfig::APP_SITE_HOST_ALIASES, for example: "
			. "public const array APP_SITE_HOST_ALIASES = ['app' => ['primary' => 'localhost', 'aliases' => []]];"
		);
	}

	public static function unmappedHostException(string $host): RuntimeException
	{
		return new RuntimeException(
			"CMS request host is not mapped to a configured site context: {$host}. "
			. "Add it to ApplicationConfig::APP_SITE_HOST_ALIASES, for example: "
			. "public const array APP_SITE_HOST_ALIASES = ['app' => ['primary' => 'localhost', 'aliases' => ['{$host}']]];"
		);
	}

	/**
	 * @param list<array<string, mixed>> $roots
	 */
	public static function hostlessSiteContextException(string $site_key, array $roots): RuntimeException
	{
		$root_names = array_map(
			static fn (array $root): string => (string) ($root['resource_name'] ?? ''),
			$roots
		);
		$root_names = array_values(array_filter($root_names, static fn (string $name): bool => $name !== ''));
		$site_key = $site_key !== '' ? $site_key : self::DEFAULT_SITE_KEY;

		return new RuntimeException(
			"Hostless CMS site context '{$site_key}' does not identify a populated root while multiple populated CMS site roots exist: "
			. implode(', ', $root_names)
			. ". Set RADAPTOR_SITE_CONTEXT or ApplicationConfig::APP_SITE_CONTEXT to one of those site keys, "
			. "or provide an HTTP host mapped through ApplicationConfig::APP_SITE_HOST_ALIASES."
		);
	}

	private static function rootHasChildren(int $root_id): bool
	{
		return ResourceTreeHandler::countChildren($root_id) > 0;
	}

	private static function resolveHostlessConfiguredContext(string $site_key): string
	{
		$site_key = self::normalizeSiteKey($site_key);

		if ($site_key === '') {
			return self::DEFAULT_SITE_KEY;
		}

		$root = self::getRootByName($site_key);

		if (is_array($root) && self::rootHasChildren((int) $root['node_id'])) {
			return $site_key;
		}

		$content_roots = self::getContentRootRows();

		if (count($content_roots) > 1) {
			throw self::hostlessSiteContextException($site_key, $content_roots);
		}

		return $site_key;
	}

	/**
	 * @param list<array<string, mixed>>|null $roots
	 */
	private static function getSingleContentRootName(?array $roots = null): ?string
	{
		$content_roots = $roots ?? self::getContentRootRows();

		if (count($content_roots) !== 1) {
			return null;
		}

		return (string) $content_roots[0]['resource_name'];
	}

	private static function getSingleRootName(): ?string
	{
		$roots = self::getRootRows();

		if (count($roots) !== 1) {
			return null;
		}

		return (string) $roots[0]['resource_name'];
	}

	private static function getConfigString(string $name, string $default): string
	{
		return self::normalizeSiteKey((string) self::getConfigValue($name, $default));
	}

	private static function getConfigValue(string $name, mixed $default): mixed
	{
		$env_value = getenv($name);

		if ($env_value !== false) {
			return $env_value;
		}

		$config_case_name = "Config::{$name}";

		if ((enum_exists('Config') || class_exists('Config')) && defined($config_case_name)) {
			$config_case = constant($config_case_name);

			if (is_object($config_case) && method_exists($config_case, 'value')) {
				return $config_case->value();
			}
		}

		$constant_name = "ApplicationConfig::{$name}";

		if (defined($constant_name)) {
			return constant($constant_name);
		}

		return $default;
	}

	/**
	 * @return array{aliases: array<string, string>, primary_hosts: array<string, string>, explicit: bool}
	 */
	private static function normalizeHostAliasConfig(): array
	{
		$value = self::getConfigValue('APP_SITE_HOST_ALIASES', []);
		$explicit = false;

		if (is_string($value)) {
			$decoded = json_decode($value, true);
			$value = is_array($decoded) ? $decoded : self::parseAliasString($value);
		}

		$aliases = [];
		$primary_hosts = [];

		if (!is_array($value)) {
			return [
				'aliases' => [],
				'primary_hosts' => [],
				'explicit' => false,
			];
		}

		foreach ($value as $key => $entry) {
			if (is_array($entry)) {
				$site_key = self::normalizeSiteKey((string) $key);
				$primary = self::normalizeHost((string) ($entry['primary'] ?? ''));

				if ($site_key === '') {
					continue;
				}

				if ($primary !== '') {
					$explicit = true;
					$aliases[$primary] = $site_key;
					$primary_hosts[$site_key] = $primary;
				}

				$entry_aliases = $entry['aliases'] ?? [];

				if (is_string($entry_aliases)) {
					$entry_aliases = array_filter(array_map('trim', explode(',', $entry_aliases)));
				}

				if (!is_array($entry_aliases)) {
					continue;
				}

				foreach ($entry_aliases as $alias) {
					$host_key = self::normalizeHost((string) $alias);

					if ($host_key === '') {
						continue;
					}

					$explicit = true;
					$aliases[$host_key] = $site_key;
					$primary_hosts[$site_key] ??= $host_key;
				}

				continue;
			}

			$host_key = self::normalizeHost((string) $key);
			$site_key = self::normalizeSiteKey((string) $entry);

			if ($host_key === '' || $site_key === '') {
				continue;
			}

			$explicit = true;
			$aliases[$host_key] = $site_key;
			$primary_hosts[$site_key] ??= $host_key;
		}

		return [
			'aliases' => $aliases,
			'primary_hosts' => $primary_hosts,
			'explicit' => $explicit,
		];
	}

	/**
	 * @return array<string, string>
	 */
	private static function parseAliasString(string $value): array
	{
		$aliases = [];

		foreach (explode(',', $value) as $pair) {
			[$host, $site_key] = array_pad(explode('=', $pair, 2), 2, '');
			$aliases[trim($host)] = trim($site_key);
		}

		return $aliases;
	}

	private static function normalizeHost(string $host): string
	{
		$host = strtolower(trim($host));

		if ($host === '') {
			return '';
		}

		if (str_contains($host, ':')) {
			$host = (string) parse_url('//' . $host, PHP_URL_HOST);
		}

		return trim($host, '.');
	}

	private static function normalizeSiteKey(?string $site_key): string
	{
		return trim((string) $site_key);
	}
}
