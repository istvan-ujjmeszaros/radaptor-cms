<?php

declare(strict_types=1);

final class LocaleHomeResourceService
{
	private static ?bool $_tablesExist = null;

	public static function refreshAll(): void
	{
		if (!self::tablesExist()) {
			return;
		}

		foreach (CmsSiteContext::getRootRows() as $root) {
			self::refreshSiteContext((string) ($root['resource_name'] ?? ''));
		}
	}

	public static function refreshForResourceId(int $resource_id): void
	{
		if ($resource_id <= 0) {
			self::refreshAll();

			return;
		}

		$site_context = ResourceLocaleService::getSiteContextForResourceId($resource_id);

		if ($site_context !== null) {
			self::refreshSiteContext($site_context);

			return;
		}

		self::refreshAll();
	}

	public static function refreshSiteContext(string $site_context): void
	{
		if (!self::tablesExist()) {
			return;
		}

		$root = CmsSiteContext::getRootByName($site_context);

		if (!is_array($root)) {
			return;
		}

		foreach (LocaleService::allForI18nMaintenance() as $locale) {
			self::refreshForLocale($site_context, $locale);
		}
	}

	public static function refreshForLocale(string $site_context, string $locale): void
	{
		if (!self::tablesExist()) {
			return;
		}

		$locale = LocaleService::canonicalize($locale);
		$root = CmsSiteContext::getRootByName($site_context);

		if (!is_array($root)) {
			return;
		}

		$computed_id = self::computeHomeResourceId($root, $locale);
		$stmt = Db::instance()->prepare(
			"INSERT INTO `locale_home_resources` (`site_context`, `locale`, `computed_resource_id`)
			VALUES (?, ?, ?)
			ON DUPLICATE KEY UPDATE `computed_resource_id` = VALUES(`computed_resource_id`)"
		);
		$stmt->execute([
			(string) ($root['resource_name'] ?? $site_context),
			$locale,
			$computed_id,
		]);
	}

	public static function getEffectiveHomeResourceId(string $site_context, string $locale): ?int
	{
		if (!self::tablesExist()) {
			return null;
		}

		$locale = LocaleService::canonicalize($locale);
		$stmt = Db::instance()->prepare(
			"SELECT `manual_resource_id`, `computed_resource_id`
			FROM `locale_home_resources`
			WHERE `site_context` = ? AND `locale` = ?"
		);
		$stmt->execute([$site_context, $locale]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!is_array($row)) {
			return null;
		}

		return self::validHomeResourceId($row['manual_resource_id'] ?? null, $site_context, $locale)
			?? self::validHomeResourceId($row['computed_resource_id'] ?? null, $site_context, $locale);
	}

	/**
	 * @param array<string, mixed> $root
	 */
	private static function computeHomeResourceId(array $root, string $locale): ?int
	{
		$rows = DbHelper::fetchAll(
			"SELECT `node_id`, `node_type`, `path`, `resource_name`, `lft`, `rgt`
				FROM `resource_tree`
					WHERE `lft` > ? AND `rgt` < ?
						AND `locale` = ?
						AND `node_type` IN ('folder', 'webpage')
					ORDER BY `lft`
					LIMIT 1",
			[(int) $root['lft'], (int) $root['rgt'], $locale]
		);

		if (!is_array($rows) || $rows === []) {
			return null;
		}

		$site_context = (string) ($root['resource_name'] ?? '');
		$row = $rows[0];

		if (($row['node_type'] ?? '') === 'webpage') {
			$node_id = (int) ($row['node_id'] ?? 0);

			return self::isValidHomeResourceData($row, $root, $site_context, $locale) ? $node_id : null;
		}

		$index_id = ResourceTreeHandler::getIndexpageNodeId((int) ($row['node_id'] ?? 0));

		if ($index_id !== null) {
			$index_data = ResourceTreeHandler::getResourceTreeEntryDataById((int) $index_id);

			return is_array($index_data) && self::isValidHomeResourceData($index_data, $root, $site_context, $locale)
				? (int) $index_id
				: null;
		}

		return null;
	}

	private static function validHomeResourceId(mixed $resource_id, string $site_context, string $locale): ?int
	{
		if (!is_numeric($resource_id) || (int) $resource_id <= 0) {
			return null;
		}

		$resource_id = (int) $resource_id;
		$root = CmsSiteContext::getRootByName($site_context);
		$resource_data = ResourceTreeHandler::getResourceTreeEntryDataById($resource_id);

		if (!is_array($root) || !is_array($resource_data)) {
			return null;
		}

		return self::isValidHomeResourceData($resource_data, $root, $site_context, $locale) ? $resource_id : null;
	}

	/**
	 * @param array<string, mixed> $resource_data
	 * @param array<string, mixed> $root
	 */
	private static function isValidHomeResourceData(array $resource_data, array $root, string $site_context, string $locale): bool
	{
		if (($resource_data['node_type'] ?? '') !== 'webpage') {
			return false;
		}

		if ((int) ($resource_data['lft'] ?? 0) <= (int) ($root['lft'] ?? 0) || (int) ($resource_data['rgt'] ?? 0) >= (int) ($root['rgt'] ?? 0)) {
			return false;
		}

		if (ResourceLocaleService::getSiteContextForResourceId((int) ($resource_data['node_id'] ?? 0)) !== $site_context) {
			return false;
		}

		if (ResourceTreeHandler::isProtectedSystemResourceData($resource_data)) {
			return false;
		}

		return ResourceLocaleService::getInheritedContentLocale((int) ($resource_data['node_id'] ?? 0)) === $locale;
	}

	private static function tablesExist(): bool
	{
		if (self::$_tablesExist !== null) {
			return self::$_tablesExist;
		}

		try {
			$pdo = Db::instance();

			return self::$_tablesExist = (
				$pdo->query("SHOW TABLES LIKE 'locale_home_resources'")->rowCount() > 0
				&& $pdo->query("SHOW TABLES LIKE 'locales'")->rowCount() > 0
			);
		} catch (Throwable) {
			return self::$_tablesExist = false;
		}
	}
}
