<?php

class CmsMenuService
{
	/**
	 * @return 'main'|'admin'
	 */
	public static function normalizeType(string $type): string
	{
		$type = strtolower(trim($type));

		if (!in_array($type, ['main', 'admin'], true)) {
			throw new InvalidArgumentException('Menu type must be either "main" or "admin".');
		}

		return $type;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function list(string $type): array
	{
		$table = self::tableFor($type);

		return DbHelper::selectManyFromQuery(
			"SELECT * FROM {$table} ORDER BY lft ASC"
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function create(
		string $type,
		string $title,
		int $parent_id = 0,
		?string $page_path = null,
		?string $url = null,
		?int $position = null
	): array {
		if (($page_path === null || trim($page_path) === '') && ($url === null || trim($url) === '')) {
			throw new InvalidArgumentException('Either page path or URL must be provided.');
		}

		$page_id = null;

		if (is_string($page_path) && trim($page_path) !== '') {
			$page = CmsPathHelper::resolveWebpage($page_path);

			if (!is_array($page)) {
				throw new RuntimeException("Webpage not found: {$page_path}");
			}

			$page_id = (int) $page['node_id'];
		}

		$table = self::tableFor($type);
		$id = NestedSet::addNode($table, $parent_id, [
			'node_type' => 'submenu',
			'node_name' => trim($title),
			'page_id' => $page_id,
			'url' => is_string($url) && trim($url) !== '' ? trim($url) : null,
		]);

		if (!is_int($id) || $id <= 0) {
			throw new RuntimeException("Unable to create {$type} menu entry.");
		}

		if ($position !== null) {
			NestedSet::moveToPosition($table, $id, $parent_id, $position);
		}

		return self::get($type, $id);
	}

	/**
	 * @param array<string, mixed> $changes
	 * @return array<string, mixed>
	 */
	public static function update(string $type, int $id, array $changes): array
	{
		$table = self::tableFor($type);
		$current = self::get($type, $id);
		$save_data = ['node_id' => $id];

		if (array_key_exists('title', $changes)) {
			$save_data['node_name'] = trim((string) $changes['title']);
		}

		if (array_key_exists('page_path', $changes)) {
			$page_path = trim((string) $changes['page_path']);

			if ($page_path === '') {
				$save_data['page_id'] = null;
			} else {
				$page = CmsPathHelper::resolveWebpage($page_path);

				if (!is_array($page)) {
					throw new RuntimeException("Webpage not found: {$page_path}");
				}

				$save_data['page_id'] = (int) $page['node_id'];
			}
		}

		if (array_key_exists('url', $changes)) {
			$url = trim((string) $changes['url']);
			$save_data['url'] = $url !== '' ? $url : null;
		}

		if (count($save_data) > 1) {
			DbHelper::updateHelper($table, $save_data, $id);
		}

		return self::get($type, $id) + ['previous' => $current];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function move(string $type, int $id, int $parent_id, int $position): array
	{
		$table = self::tableFor($type);

		if (!NestedSet::moveToPosition($table, $id, $parent_id, $position)) {
			throw new RuntimeException("Unable to move {$type} menu entry {$id}.");
		}

		return self::get($type, $id);
	}

	public static function delete(string $type, int $id, bool $recursive = false): bool
	{
		$table = self::tableFor($type);

		return $recursive
			? NestedSet::deleteNodeRecursive($table, $id) > 0
			: NestedSet::deleteNode($table, $id);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get(string $type, int $id): array
	{
		$table = self::tableFor($type);
		$values = DbHelper::selectOne($table, ['node_id' => $id]);

		if (!is_array($values)) {
			throw new RuntimeException("Menu entry not found: {$id}");
		}

		return $values;
	}

	private static function tableFor(string $type): string
	{
		return match (self::normalizeType($type)) {
			'main' => 'mainmenu_tree',
			'admin' => 'adminmenu_tree',
		};
	}
}
