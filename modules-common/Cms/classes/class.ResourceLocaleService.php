<?php

declare(strict_types=1);

final class ResourceLocaleService
{
	public static function getInheritedContentLocale(int $resource_id): ?string
	{
		if (!self::hasResourceLocaleColumn()) {
			return null;
		}

		$data = ResourceTreeHandler::getResourceTreeEntryDataById($resource_id);

		if (!is_array($data)) {
			return null;
		}

		$own_locale = LocaleService::tryCanonicalize((string) ($data['locale'] ?? ''));

		if ($own_locale !== null) {
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
			return null;
		}

		return LocaleService::tryCanonicalize((string) ($row['locale'] ?? ''));
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

	private static function hasResourceLocaleColumn(): bool
	{
		try {
			$stmt = Db::instance()->prepare("SHOW COLUMNS FROM `resource_tree` LIKE 'locale'");
			$stmt->execute();

			return $stmt->rowCount() > 0;
		} catch (Throwable) {
			return false;
		}
	}
}
