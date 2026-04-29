<?php

declare(strict_types=1);

class CmsAuthoringQueryHelper
{
	/**
	 * @return array<string, mixed>|null
	 */
	public static function resolveNodeFromPath(string $path): ?array
	{
		$normalized_path = self::normalizePath($path);
		$path_parts = explode('/', trim($normalized_path, '/'));
		$resource_name = array_pop($path_parts);

		if ($resource_name === '') {
			$resource_name = 'index.html';
		}

		$folder = '/' . implode('/', $path_parts);

		if ($folder !== '/') {
			$folder .= '/';
		}

		$node = ResourceTreeHandler::getResourceTreeEntryData($folder, $resource_name);

		if ($node !== null) {
			return $node;
		}

		if ($path_parts === []) {
			return null;
		}

		$resource_name = array_pop($path_parts);
		$folder = '/' . implode('/', $path_parts);

		if ($folder !== '/') {
			$folder .= '/';
		}

		return ResourceTreeHandler::getResourceTreeEntryData($folder, $resource_name);
	}

	/**
	 * @return list<array{node_id: int, resource_name: string, path: string, node_type: string}>
	 */
	public static function getWebpagesUnderPath(string $base_path): array
	{
		$domain_context = Config::APP_DOMAIN_CONTEXT->value();
		$root_id = ResourceTreeHandler::getDomainRoot($domain_context);

		if ($root_id === null) {
			return [];
		}

		$parent_id = $root_id;

		if ($base_path !== '/') {
			$node = self::resolveNodeFromPath($base_path);

			if ($node === null) {
				throw new RuntimeException("Path not found: {$base_path}");
			}

			$parent_id = (int) $node['node_id'];
		}

		$parent_data = ResourceTreeHandler::getResourceTreeEntryDataById($parent_id);

		if ($parent_data === null) {
			return [];
		}

		$stmt = DbHelper::prexecute(
			"SELECT node_id, resource_name, path, node_type
			 FROM resource_tree
			 WHERE lft >= ? AND rgt <= ? AND node_type = 'webpage'
			 ORDER BY path, resource_name",
			[$parent_data['lft'], $parent_data['rgt']]
		);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * @return list<array{page_id: int, path: string, slot: string, seq: int, connection_id: int}>
	 */
	public static function getWidgetPlacements(string $widget_name): array
	{
		$rows = DbHelper::selectMany('widget_connections', [
			'widget_name' => $widget_name,
		]);

		if (empty($rows)) {
			return [];
		}

		$placements = [];

		foreach ($rows as $row) {
			$page_id = (int) $row['page_id'];
			$path = Url::getSeoUrl($page_id, false) ?? ResourceTreeHandler::getPathFromId($page_id);

			if ($path === '') {
				continue;
			}

			$placements[] = [
				'page_id' => $page_id,
				'path' => $path,
				'slot' => (string) ($row['slot_name'] ?? ''),
				'seq' => (int) ($row['seq'] ?? 0),
				'connection_id' => (int) $row['connection_id'],
			];
		}

		usort(
			$placements,
			static fn (array $a, array $b): int => [$a['path'], $a['slot'], $a['seq']]
				<=> [$b['path'], $b['slot'], $b['seq']]
		);

		return $placements;
	}

	public static function normalizePath(string $path): string
	{
		$path = trim($path);

		if ($path === '') {
			return '/';
		}

		if (!str_starts_with($path, '/')) {
			$path = '/' . $path;
		}

		return preg_replace('#/+#', '/', $path) ?? '/';
	}
}
