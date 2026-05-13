<?php

declare(strict_types=1);

final class ResourceLocaleService
{
	private const string REQUEST_CACHE_KEY = 'cms.resource_locale.inherited_content_locale';
	private static ?bool $_hasResourceLocaleColumn = null;

	public static function getInheritedContentLocale(int $resource_id): ?string
	{
		if ($resource_id <= 0) {
			return null;
		}

		if (!self::hasResourceLocaleColumn()) {
			return null;
		}

		$cached = self::getCachedInheritedContentLocale($resource_id);

		if ($cached['hit']) {
			return $cached['locale'];
		}

		$data = ResourceTreeHandler::getResourceTreeEntryDataById($resource_id);

		if (!is_array($data)) {
			self::setCachedInheritedContentLocale($resource_id, null);

			return null;
		}

		$own_locale = LocaleService::tryCanonicalize((string) ($data['locale'] ?? ''));

		if ($own_locale !== null) {
			self::setCachedInheritedContentLocale($resource_id, $own_locale);

			return $own_locale;
		}

		$row = DbHelper::fetch(
			"SELECT `locale`
			FROM `resource_tree`
			WHERE `lft` < ? AND `rgt` > ? AND `locale` IS NOT NULL AND `locale` <> ''
			ORDER BY `lft` DESC
			LIMIT 1",
			[(int) $data['lft'], (int) $data['rgt']]
		);

		if (!is_array($row)) {
			self::setCachedInheritedContentLocale($resource_id, null);

			return null;
		}

		$locale = LocaleService::tryCanonicalize((string) ($row['locale'] ?? ''));
		self::setCachedInheritedContentLocale($resource_id, $locale);

		return $locale;
	}

	public static function getRenderLocale(int $resource_id): string
	{
		return self::getInheritedContentLocale($resource_id) ?? Kernel::getLocale();
	}

	public static function getSiteContextForResourceId(int $resource_id): ?string
	{
		$data = ResourceTreeHandler::getResourceTreeEntryDataById($resource_id);

		if (!is_array($data)) {
			return null;
		}

		if (($data['node_type'] ?? '') === 'root') {
			return (string) ($data['resource_name'] ?? '');
		}

		return ResourceTreeHandler::getDomainContextForResourceTreeEntryData($data);
	}

	public static function hasResourceLocaleColumn(): bool
	{
		if (self::$_hasResourceLocaleColumn === true) {
			return true;
		}

		try {
			$stmt = Db::instance()->prepare("SHOW COLUMNS FROM `resource_tree` LIKE 'locale'");
			$stmt->execute();

			return self::$_hasResourceLocaleColumn = $stmt->rowCount() > 0;
		} catch (Throwable) {
			self::$_hasResourceLocaleColumn = false;

			return false;
		}
	}

	public static function resetRequestCache(): void
	{
		try {
			unset(RequestContextHolder::current()->inMemoryCache[self::REQUEST_CACHE_KEY]);
		} catch (Throwable) {
		}
	}

	/**
	 * @return array{hit: bool, locale: ?string}
	 */
	private static function getCachedInheritedContentLocale(int $resource_id): array
	{
		try {
			$cache = RequestContextHolder::current()->inMemoryCache[self::REQUEST_CACHE_KEY] ?? [];
		} catch (Throwable) {
			return ['hit' => false, 'locale' => null];
		}

		if (!is_array($cache) || !array_key_exists($resource_id, $cache)) {
			return ['hit' => false, 'locale' => null];
		}

		$locale = $cache[$resource_id];

		return [
			'hit' => true,
			'locale' => is_string($locale) ? $locale : null,
		];
	}

	private static function setCachedInheritedContentLocale(int $resource_id, ?string $locale): void
	{
		try {
			RequestContextHolder::current()->inMemoryCache[self::REQUEST_CACHE_KEY][$resource_id] = $locale;
		} catch (Throwable) {
		}
	}
}
