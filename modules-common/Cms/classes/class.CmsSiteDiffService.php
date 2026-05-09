<?php

declare(strict_types=1);

final class CmsSiteDiffService
{
	private const int SCHEMA_VERSION = 1;
	private const int MAX_KEYS_PER_BUCKET = 50;

	/**
	 * @param array<string, mixed> $baseline
	 * @param array<string, mixed> $current
	 * @return array<string, mixed>
	 */
	public static function diffSnapshots(array $baseline, array $current): array
	{
		$baseline_tables = is_array($baseline['tables'] ?? null) ? $baseline['tables'] : [];
		$current_tables = is_array($current['tables'] ?? null) ? $current['tables'] : [];
		$table_names = array_values(array_unique([...array_keys($baseline_tables), ...array_keys($current_tables)]));
		sort($table_names, SORT_STRING);

		$tables = [];
		$summary = [
			'tables_added' => 0,
			'tables_removed' => 0,
			'tables_changed' => 0,
			'rows_added' => 0,
			'rows_removed' => 0,
			'rows_changed' => 0,
		];

		foreach ($table_names as $table) {
			$baseline_rows = self::normalizeRows(is_array($baseline_tables[$table] ?? null) ? $baseline_tables[$table] : []);
			$current_rows = self::normalizeRows(is_array($current_tables[$table] ?? null) ? $current_tables[$table] : []);
			$primary_keys = self::primaryKeysForTable($table, $baseline, $current);
			$baseline_map = self::mapRows($table, $baseline_rows, $primary_keys, $baseline);
			$current_map = self::mapRows($table, $current_rows, $primary_keys, $current);
			$missing = array_values(array_diff(array_keys($baseline_map), array_keys($current_map)));
			$extra = array_values(array_diff(array_keys($current_map), array_keys($baseline_map)));
			$changed = [];

			foreach (array_intersect(array_keys($baseline_map), array_keys($current_map)) as $key) {
				if (!hash_equals($baseline_map[$key], $current_map[$key])) {
					$changed[] = $key;
				}
			}

			sort($missing, SORT_STRING);
			sort($extra, SORT_STRING);
			sort($changed, SORT_STRING);

			if (!isset($baseline_tables[$table])) {
				++$summary['tables_added'];
			} elseif (!isset($current_tables[$table])) {
				++$summary['tables_removed'];
			}

			if ($missing !== [] || $extra !== [] || $changed !== []) {
				++$summary['tables_changed'];
			}

			$summary['rows_removed'] += count($missing);
			$summary['rows_added'] += count($extra);
			$summary['rows_changed'] += count($changed);

			$tables[$table] = [
				'primary_keys' => $primary_keys,
				'baseline_count' => count($baseline_rows),
				'current_count' => count($current_rows),
				'missing_count' => count($missing),
				'extra_count' => count($extra),
				'changed_count' => count($changed),
				'missing_keys' => array_slice($missing, 0, self::MAX_KEYS_PER_BUCKET),
				'extra_keys' => array_slice($extra, 0, self::MAX_KEYS_PER_BUCKET),
				'changed_keys' => array_slice($changed, 0, self::MAX_KEYS_PER_BUCKET),
			];
		}

		$result = [
			'schema_version' => self::SCHEMA_VERSION,
			'status' => array_sum($summary) === 0 ? 'success' : 'different',
			'summary' => $summary,
			'tables' => $tables,
		];
		self::ksortRecursive($result);

		return $result;
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @return array<string, mixed>
	 */
	public static function diffLive(array $baseline): array
	{
		return self::diffSnapshots($baseline, CmsSiteSnapshotService::exportSnapshot(true));
	}

	/**
	 * @param array<int, mixed> $rows
	 * @return list<array<string, mixed>>
	 */
	private static function normalizeRows(array $rows): array
	{
		$normalized = [];

		foreach ($rows as $row) {
			if (is_array($row)) {
				$normalized[] = $row;
			}
		}

		return $normalized;
	}

	/**
	 * @param array<string, mixed> $baseline
	 * @param array<string, mixed> $current
	 * @return list<string>
	 */
	private static function primaryKeysForTable(string $table, array $baseline, array $current): array
	{
		foreach ([$baseline, $current] as $snapshot) {
			$primary_keys = $snapshot['schema']['tables'][$table]['primary_keys'] ?? null;

			if (is_array($primary_keys) && $primary_keys !== []) {
				return array_values(array_map('strval', $primary_keys));
			}
		}

		return [];
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @param list<string> $primary_keys
	 * @return array<string, string>
	 */
	private static function mapRows(string $table, array $rows, array $primary_keys, array $snapshot): array
	{
		$mapped = [];

		foreach ($rows as $index => $row) {
			$key = self::logicalRowKey($table, $row, $primary_keys, $snapshot, $index);
			$copy = self::normalizeRowForHash($table, $row, $snapshot);
			self::ksortRecursive($copy);
			$base_key = $key;
			$duplicate_index = 2;

			while (isset($mapped[$key])) {
				$key = $base_key . '#' . $duplicate_index;
				++$duplicate_index;
			}

			$mapped[$key] = hash('sha256', json_encode($copy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
		}

		ksort($mapped, SORT_STRING);

		return $mapped;
	}

	/**
	 * @param array<string, mixed> $row
	 * @param list<string> $primary_keys
	 * @param array<string, mixed> $snapshot
	 */
	private static function logicalRowKey(string $table, array $row, array $primary_keys, array $snapshot, int $index): string
	{
		return match ($table) {
			'resource_tree' => 'resource:' . self::resourcePathFromRow($row),
			'widget_connections' => 'widget:' . self::widgetConnectionLogicalKey($row, $snapshot),
			'resource_acl' => 'acl:' . self::resourcePathForId($snapshot, (int) ($row['resource_id'] ?? 0))
				. '|subject=' . (string) ($row['subject_type'] ?? '') . ':' . (string) ($row['subject_id'] ?? ''),
			'attributes' => 'attribute:' . self::attributeLogicalKey($row, $snapshot),
			'mediacontainer_vfs_files' => 'media:' . (string) ($row['storage_folder_id'] ?? '')
				. ':' . (string) ($row['md5_hash'] ?? '')
				. ':' . (string) ($row['filename'] ?? $row['original_filename'] ?? ''),
			default => $primary_keys === []
				? 'row:' . $index
				: implode('|', array_map(static fn (string $column): string => $column . '=' . (string) ($row[$column] ?? ''), $primary_keys)),
		};
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<string, mixed> $snapshot
	 * @return array<string, mixed>
	 */
	private static function normalizeRowForHash(string $table, array $row, array $snapshot): array
	{
		switch ($table) {
			case 'resource_tree':
				self::unsetKeys($row, ['node_id', 'parent_id', 'lft', 'rgt', 'last_modified']);

				break;

			case 'widget_connections':
				self::unsetKeys($row, ['connection_id', 'page_id']);

				break;

			case 'resource_acl':
				self::unsetKeys($row, ['acl_id', 'resource_id']);

				break;

			case 'attributes':
				self::unsetKeys($row, ['id', 'resource_id']);

				break;

			case 'mediacontainer_vfs_files':
				self::unsetKeys($row, ['file_id']);

				break;
		}

		if ($table === 'widget_connections') {
			$row['_page_path'] = self::resourcePathForId($snapshot, (int) ($row['page_id'] ?? 0));
		}

		if ($table === 'resource_acl') {
			$row['_resource_path'] = self::resourcePathForId($snapshot, (int) ($row['resource_id'] ?? 0));
		}

		if ($table === 'attributes') {
			$row['_logical_target'] = self::attributeTargetKey($row, $snapshot);
		}

		return $row;
	}

	/**
	 * @param array<string, mixed> $row
	 * @param list<string> $keys
	 */
	private static function unsetKeys(array &$row, array $keys): void
	{
		foreach ($keys as $key) {
			unset($row[$key]);
		}
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private static function resourcePathFromRow(array $row): string
	{
		$type = (string) ($row['node_type'] ?? '');
		$path = (string) ($row['path'] ?? '/');
		$name = (string) ($row['resource_name'] ?? '');

		if ($type === 'root') {
			return '/';
		}

		if ($type === 'folder') {
			return rtrim($path . $name, '/') . '/';
		}

		return $path . $name;
	}

	/**
	 * @param array<string, mixed> $snapshot
	 */
	private static function resourcePathForId(array $snapshot, int $resource_id): string
	{
		foreach (($snapshot['tables']['resource_tree'] ?? []) as $row) {
			if (is_array($row) && (int) ($row['node_id'] ?? 0) === $resource_id) {
				return self::resourcePathFromRow($row);
			}
		}

		return 'resource_id=' . $resource_id;
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<string, mixed> $snapshot
	 */
	private static function widgetConnectionLogicalKey(array $row, array $snapshot): string
	{
		return self::resourcePathForId($snapshot, (int) ($row['page_id'] ?? 0))
			. '|slot=' . (string) ($row['slot_name'] ?? '')
			. '|seq=' . (string) ($row['seq'] ?? '')
			. '|widget=' . (string) ($row['widget_name'] ?? '');
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<string, mixed> $snapshot
	 */
	private static function attributeLogicalKey(array $row, array $snapshot): string
	{
		return self::attributeTargetKey($row, $snapshot)
			. '|param=' . (string) ($row['param_name'] ?? '');
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<string, mixed> $snapshot
	 */
	private static function attributeTargetKey(array $row, array $snapshot): string
	{
		$resource_name = (string) ($row['resource_name'] ?? '');
		$resource_id = (int) ($row['resource_id'] ?? 0);

		if ($resource_name === 'resource_data') {
			return 'resource:' . self::resourcePathForId($snapshot, $resource_id);
		}

		if ($resource_name === 'widget_connection' || str_starts_with($resource_name, '_')) {
			foreach (($snapshot['tables']['widget_connections'] ?? []) as $connection) {
				if (is_array($connection) && (int) ($connection['connection_id'] ?? 0) === $resource_id) {
					return 'widget:' . self::widgetConnectionLogicalKey($connection, $snapshot) . '|resource=' . $resource_name;
				}
			}
		}

		return $resource_name . ':' . $resource_id;
	}

	/**
	 * @param array<string|int, mixed> $value
	 */
	private static function ksortRecursive(array &$value): void
	{
		if (!array_is_list($value)) {
			ksort($value);
		}

		foreach ($value as &$child) {
			if (is_array($child)) {
				self::ksortRecursive($child);
			}
		}
	}
}
