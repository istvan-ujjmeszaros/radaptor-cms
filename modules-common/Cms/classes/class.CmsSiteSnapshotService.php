<?php

declare(strict_types=1);

class CmsSiteSnapshotService
{
	public const string FORMAT = 'radaptor.site_snapshot';
	public const int VERSION = 1;

	private const array PREFERRED_TABLE_ORDER = [
		'roles_tree',
		'usergroups_tree',
		'users',
		'usergroups_roles_mapping',
		'users_roles_mapping',
		'users_usergroups_mapping',
		'config_app',
		'config_user',
		'resource_tree',
		'mediacontainer_vfs_files',
		'resource_acl',
		'attributes',
		'widget_connections',
		'richtext',
		'adminmenu_tree',
		'mainmenu_tree',
		'blog',
		'comments',
		'tags',
		'tag_connections',
		'custom_queries',
		'i18n_messages',
		'i18n_translations',
	];

	private const array EXCLUDED_OPERATIONAL_TABLES = [
		'migrations',
		'seeds',
		'i18n_build_state',
		'i18n_tm_entries',
		'email_outbox',
		'email_outbox_recipients',
		'email_queue_transactional',
		'email_queue_archive',
		'email_queue_dead_letter',
		'mcp_tokens',
		'mcp_audit',
	];

	/**
	 * @return array<string, mixed>
	 */
	public static function exportSnapshot(bool $uploads_backed_up): array
	{
		if (!$uploads_backed_up) {
			throw new InvalidArgumentException('Uploaded files must be backed up before exporting a site snapshot. Re-run with --uploads-backed-up after copying the upload directory.');
		}

		$uploads_report = self::checkUploads();

		if (!$uploads_report['ok']) {
			throw new RuntimeException('Uploaded file consistency check failed. Fix or back up the upload directory before exporting the snapshot.');
		}

		$tables = self::getSnapshotTables();
		$schema = self::buildSchema($tables);
		$table_rows = [];
		$table_counts = [];

		foreach ($tables as $table) {
			$rows = self::fetchTableRows($table);
			$table_rows[$table] = $rows;
			$table_counts[$table] = count($rows);
		}

		return [
			'format' => self::FORMAT,
			'version' => self::VERSION,
			'created_at' => gmdate(DATE_ATOM),
			'app' => [
				'domain_context' => Config::APP_DOMAIN_CONTEXT->value(),
				'site_context' => class_exists(CmsSiteContext::class)
					? CmsSiteContext::getConfiguredSiteKey()
					: Config::APP_DOMAIN_CONTEXT->value(),
			],
			'schema' => $schema,
			'table_counts' => $table_counts,
			'tables' => $table_rows,
			'uploads' => [
				'directory' => self::getUploadDirectoryRelativePath(),
				'backed_up_confirmed' => true,
				'files' => $uploads_report['manifest'],
			],
			'excluded_operational_tables' => self::EXCLUDED_OPERATIONAL_TABLES,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function writeSnapshot(string $output_path, bool $uploads_backed_up): array
	{
		$snapshot = self::exportSnapshot($uploads_backed_up);
		$target = self::resolveDeployPath($output_path);
		$directory = dirname($target);

		if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
			throw new RuntimeException("Unable to create snapshot output directory: {$directory}");
		}

		$bytes_written = file_put_contents($target, self::encodeSnapshot($snapshot), LOCK_EX);

		if ($bytes_written === false) {
			throw new RuntimeException("Unable to write site snapshot: {$target}");
		}

		return [
			'output' => $target,
			'bytes' => $bytes_written,
			'table_counts' => $snapshot['table_counts'],
			'upload_count' => count($snapshot['uploads']['files']),
			'schema_signature' => $snapshot['schema']['signature'],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function loadSnapshotFile(string $snapshot_path): array
	{
		$path = self::resolveDeployPath($snapshot_path);

		if (!is_file($path)) {
			throw new RuntimeException("Site snapshot file not found: {$path}");
		}

		$json = file_get_contents($path);

		if ($json === false) {
			throw new RuntimeException("Unable to read site snapshot file: {$path}");
		}

		return self::decodeSnapshot($json);
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @return array<string, mixed>
	 */
	public static function importSnapshot(array $snapshot, bool $dry_run, bool $replace): array
	{
		self::validateSnapshot($snapshot);

		$tables = array_keys($snapshot['tables']);
		$current_schema = self::buildSchema($tables);
		$errors = [];

		if (($snapshot['schema']['signature'] ?? '') !== $current_schema['signature']) {
			$errors[] = 'Snapshot schema signature does not match the current database schema.';
		}

		$uploads_report = self::checkUploads($snapshot);

		if (!$uploads_report['ok']) {
			$errors[] = 'Uploaded files are not consistent with the snapshot. Copy the upload directory first, then re-run the import.';
		}

		if (!$dry_run && !$replace) {
			$errors[] = 'Refusing destructive import without --replace.';
		}

		$summary = self::buildImportSummary($snapshot);

		if ($dry_run || $errors !== []) {
			return [
				'status' => $errors === [] ? 'success' : 'error',
				'dry_run' => $dry_run,
				'applied' => false,
				'errors' => $errors,
				'summary' => $summary,
				'uploads' => self::summarizeUploadsReport($uploads_report),
			];
		}

		self::replaceTables($snapshot);
		$post_import_maintenance = self::runPostImportMaintenance();
		$errors = [
			...$errors,
			...self::collectPostImportMaintenanceErrors($post_import_maintenance),
		];

		$tree_reports = self::checkSnapshotTrees();
		$tree_ok = array_reduce(
			$tree_reports,
			static fn (bool $carry, array $report): bool => $carry && (bool) ($report['ok'] ?? false),
			true
		);

		if (!$tree_ok) {
			$errors[] = 'Nested-set consistency check failed after import.';
		}

		return [
			'status' => $errors === [] ? 'success' : 'error',
			'dry_run' => false,
			'applied' => true,
			'errors' => $errors,
			'summary' => $summary,
			'uploads' => self::summarizeUploadsReport(self::checkUploads($snapshot)),
			'post_import_build' => $post_import_maintenance['steps']['build_all']['result'] ?? null,
			'i18n_shipped_sync' => $post_import_maintenance['steps']['i18n_shipped_sync']['result'] ?? null,
			'i18n_tag_sync' => $post_import_maintenance['steps']['i18n_tag_sync']['result'] ?? null,
			'i18n_tm_rebuild' => $post_import_maintenance['steps']['i18n_tm_rebuild']['result'] ?? null,
			'post_import_maintenance' => $post_import_maintenance,
			'trees' => $tree_reports,
		];
	}

	/**
	 * @param array<string, mixed>|null $snapshot
	 * @return array<string, mixed>
	 */
	public static function checkUploads(?array $snapshot = null): array
	{
		$manifest = $snapshot === null
			? self::buildUploadManifestFromDatabase()
			: self::extractUploadManifestFromSnapshot($snapshot);

		$files = [];
		$missing = 0;
		$mismatched = 0;
		$present = 0;

		foreach ($manifest as $file) {
			$path = self::getUploadDirectoryPath()
				. '/'
				. (int) ($file['storage_folder_id'] ?? 0)
				. '/'
				. (string) ($file['md5_hash'] ?? '');
			$physical_exists = is_file($path);
			$actual_size = $physical_exists ? filesize($path) : false;
			$actual_md5 = $physical_exists ? md5_file($path) : false;
			$size_matches = $physical_exists && (int) ($file['filesize'] ?? 0) === (int) $actual_size;
			$hash_matches = $physical_exists && (string) ($file['md5_hash'] ?? '') === (string) $actual_md5;
			$status = 'present';

			if (!$physical_exists) {
				$status = 'missing';
				++$missing;
			} elseif (!$size_matches || !$hash_matches) {
				$status = 'mismatched';
				++$mismatched;
			} else {
				++$present;
			}

			$files[] = $file + [
				'physical_path' => $path,
				'physical_exists' => $physical_exists,
				'actual_filesize' => $actual_size === false ? null : (int) $actual_size,
				'actual_md5_hash' => $actual_md5 === false ? null : (string) $actual_md5,
				'status' => $status,
			];
		}

		return [
			'ok' => $missing === 0 && $mismatched === 0,
			'upload_directory' => self::getUploadDirectoryPath(),
			'total' => count($manifest),
			'present' => $present,
			'missing' => $missing,
			'mismatched' => $mismatched,
			'manifest' => $manifest,
			'files' => $files,
		];
	}

	/**
	 * @param array<string, mixed> $report
	 * @return array<string, mixed>
	 */
	public static function summarizeUploadsReport(array $report, bool $include_all_files = false): array
	{
		$summary = [
			'ok' => (bool) ($report['ok'] ?? false),
			'upload_directory' => (string) ($report['upload_directory'] ?? ''),
			'total' => (int) ($report['total'] ?? 0),
			'present' => (int) ($report['present'] ?? 0),
			'missing' => (int) ($report['missing'] ?? 0),
			'mismatched' => (int) ($report['mismatched'] ?? 0),
		];
		$files = $report['files'] ?? [];

		if (!is_array($files)) {
			return $summary;
		}

		if ($include_all_files) {
			$summary['files'] = $files;

			return $summary;
		}

		$problem_files = array_values(array_filter(
			$files,
			static fn (mixed $file): bool => is_array($file) && ($file['status'] ?? 'present') !== 'present'
		));

		if ($problem_files !== []) {
			$summary['problem_files_total'] = count($problem_files);
			$summary['problem_files'] = array_slice($problem_files, 0, 20);

			if (count($problem_files) > 20) {
				$summary['problem_files_truncated'] = count($problem_files) - 20;
			}
		}

		return $summary;
	}

	/**
	 * @param array<string, mixed> $snapshot
	 */
	public static function encodeSnapshot(array $snapshot): string
	{
		return json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function decodeSnapshot(string $json): array
	{
		$decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

		if (!is_array($decoded)) {
			throw new InvalidArgumentException('Site snapshot JSON must decode to an object.');
		}

		return $decoded;
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @return array<string, int>
	 */
	private static function buildImportSummary(array $snapshot): array
	{
		$summary = [];

		foreach ($snapshot['tables'] as $table => $rows) {
			$summary[(string) $table] = is_array($rows) ? count($rows) : 0;
		}

		return $summary;
	}

	/**
	 * @param array<string, mixed> $snapshot
	 */
	private static function replaceTables(array $snapshot): void
	{
		self::validateSnapshotTableRows($snapshot);

		$pdo = Db::instance();
		$tables = array_keys($snapshot['tables']);
		$schema = self::buildSchema($tables);
		$transaction_started = false;

		$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

		try {
			if (!$pdo->inTransaction()) {
				$pdo->beginTransaction();
				$transaction_started = true;
			}

			foreach (array_reverse($tables) as $table) {
				$pdo->exec('DELETE FROM ' . self::quoteIdentifier($table));
			}

			foreach ($tables as $table) {
				/** @var list<array<string, mixed>> $rows */
				$rows = $snapshot['tables'][$table];
				self::insertTableRows($table, $schema['tables'][$table]['columns'], $rows);
			}

			if ($transaction_started) {
				$pdo->commit();
			}
		} catch (Throwable $exception) {
			if ($transaction_started && $pdo->inTransaction()) {
				$pdo->rollBack();
			}

			throw $exception;
		} finally {
			$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
		}

		foreach ($tables as $table) {
			self::resetAutoIncrement($table, $schema['tables'][$table]['columns']);
		}

		Cache::flush();
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function syncShippedTranslationsAfterImport(): ?array
	{
		if (!class_exists(I18nShippedSyncService::class)) {
			return null;
		}

		return I18nShippedSyncService::sync([
			'mode' => class_exists(CsvImportMode::class) ? CsvImportMode::Upsert->value : 'upsert',
			'dry_run' => false,
			'build' => true,
		]);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function syncTagI18nMessagesAfterImport(): ?array
	{
		if (!class_exists(EntityTag::class)) {
			return null;
		}

		return EntityTag::syncAllTagI18nMessages(false);
	}

	/**
	 * @return array<string, int>|null
	 */
	private static function rebuildTranslationMemoryAfterImport(): ?array
	{
		if (!class_exists(I18nTm::class)) {
			return null;
		}

		return [
			'rebuilt_rows' => I18nTm::rebuildFromTranslations(),
		];
	}

	/**
	 * @return array{
	 *     ran: bool,
	 *     success: bool,
	 *     steps: array<string, array<string, mixed>>
	 * }
	 */
	private static function runPostImportMaintenance(): array
	{
		$steps = [
			'i18n_tag_sync' => self::runPostImportMaintenanceStep(
				'i18n:sync-tags',
				'Tag i18n sync',
				static fn (): ?array => self::syncTagI18nMessagesAfterImport()
			),
			'i18n_shipped_sync' => self::runPostImportMaintenanceStep(
				'i18n:sync-shipped',
				'Shipped i18n sync',
				static fn (): ?array => self::summarizeI18nShippedSyncResult(self::syncShippedTranslationsAfterImport()),
				static fn (mixed $result): bool => !is_array($result) || ($result['has_errors'] ?? false) !== true,
				'Shipped i18n sync reported errors.'
			),
			'i18n_tm_rebuild' => self::runPostImportMaintenanceStep(
				'i18n:tm-rebuild',
				'Translation memory rebuild',
				static fn (): ?array => self::rebuildTranslationMemoryAfterImport()
			),
			'build_all' => self::runPostImportMaintenanceStep(
				'build:all',
				'Build all',
				static fn (): array => self::runBuildAllAfterImport()
			),
			'cache_flush' => self::runPostImportMaintenanceStep(
				'cache:flush',
				'Cache flush',
				static function (): array {
					Cache::flush();

					return ['flushed' => true];
				}
			),
		];

		return [
			'ran' => true,
			'success' => array_reduce(
				$steps,
				static fn (bool $carry, array $step): bool => $carry && (bool) ($step['success'] ?? false),
				true
			),
			'steps' => $steps,
		];
	}

	/**
	 * @param callable(): mixed $callback
	 * @param (callable(mixed): bool)|null $isSuccess
	 * @return array<string, mixed>
	 */
	private static function runPostImportMaintenanceStep(
		string $command,
		string $label,
		callable $callback,
		?callable $isSuccess = null,
		?string $failureMessage = null
	): array {
		try {
			$result = $callback();
			$success = $isSuccess === null ? true : $isSuccess($result);

			return [
				'command' => $command,
				'label' => $label,
				'ran' => true,
				'success' => $success,
				'message' => $success ? null : ($failureMessage ?? "{$label} failed."),
				'result' => $result,
			];
		} catch (Throwable $exception) {
			return [
				'command' => $command,
				'label' => $label,
				'ran' => true,
				'success' => false,
				'message' => $exception->getMessage(),
				'result' => null,
			];
		}
	}

	/**
	 * @param array<string, mixed> $maintenance
	 * @return list<string>
	 */
	private static function collectPostImportMaintenanceErrors(array $maintenance): array
	{
		$errors = [];

		foreach (($maintenance['steps'] ?? []) as $step) {
			if (!is_array($step) || ($step['success'] ?? false) === true) {
				continue;
			}

			$label = (string) ($step['label'] ?? 'Maintenance step');
			$message = trim((string) ($step['message'] ?? 'failed'));
			$errors[] = "Post-import {$label} failed: {$message}";
		}

		return $errors;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function runBuildAllAfterImport(): array
	{
		if (!class_exists('CLICommandBuildAll')) {
			throw new RuntimeException('CLICommandBuildAll is not available.');
		}

		ob_start();

		try {
			CLICommandBuildAll::create();
		} finally {
			$output = (string) ob_get_clean();
		}

		$lines = array_values(array_filter(
			preg_split('/\R/', trim($output)) ?: [],
			static fn (string $line): bool => trim($line) !== ''
		));

		return [
			'ran' => true,
			'success' => true,
			'output_line_count' => count($lines),
			'output_tail' => array_slice($lines, -20),
		];
	}

	/**
	 * @param array<string, mixed>|null $result
	 * @return array<string, mixed>|null
	 */
	private static function summarizeI18nShippedSyncResult(?array $result): ?array
	{
		if ($result === null) {
			return null;
		}

		$groups = [];

		foreach (($result['groups'] ?? []) as $group) {
			if (!is_array($group)) {
				continue;
			}

			$groups[] = [
				'group_type' => (string) ($group['group_type'] ?? ''),
				'group_id' => (string) ($group['group_id'] ?? ''),
				'status' => (string) ($group['status'] ?? ''),
				'files_processed' => (int) ($group['files_processed'] ?? 0),
				'conflicts' => (int) ($group['conflicts'] ?? 0),
				'inserted' => (int) ($group['inserted'] ?? 0),
				'updated' => (int) ($group['updated'] ?? 0),
				'imported' => (int) ($group['imported'] ?? 0),
				'skipped' => (int) ($group['skipped'] ?? 0),
				'deleted' => (int) ($group['deleted'] ?? 0),
				'has_errors' => (bool) ($group['has_errors'] ?? false),
				'errors' => is_array($group['errors'] ?? null) ? $group['errors'] : [],
			];
		}

		return [
			'dry_run' => (bool) ($result['dry_run'] ?? false),
			'mode' => (string) ($result['mode'] ?? ''),
			'build_requested' => (bool) ($result['build_requested'] ?? false),
			'build_ran' => (bool) ($result['build_ran'] ?? false),
			'built_locales' => is_array($result['built_locales'] ?? null) ? $result['built_locales'] : [],
			'groups_processed' => (int) ($result['groups_processed'] ?? 0),
			'files_processed' => (int) ($result['files_processed'] ?? 0),
			'conflicts' => (int) ($result['conflicts'] ?? 0),
			'inserted' => (int) ($result['inserted'] ?? 0),
			'updated' => (int) ($result['updated'] ?? 0),
			'imported' => (int) ($result['imported'] ?? 0),
			'skipped' => (int) ($result['skipped'] ?? 0),
			'deleted' => (int) ($result['deleted'] ?? 0),
			'has_errors' => (bool) ($result['has_errors'] ?? false),
			'groups' => $groups,
		];
	}

	/**
	 * @param list<string> $tables
	 * @return array<string, mixed>
	 */
	private static function buildSchema(array $tables): array
	{
		$schema_tables = [];

		foreach ($tables as $table) {
			$schema_tables[$table] = [
				'columns' => self::getTableColumns($table),
				'primary_keys' => self::getPrimaryKeyColumns($table),
				'auto_increment_column' => self::getAutoIncrementColumn($table),
			];
		}

		ksort($schema_tables);

		$payload = [
			'tables' => $schema_tables,
		];

		return [
			'signature' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
			'tables' => $schema_tables,
		];
	}

	/**
	 * @return list<string>
	 */
	private static function getSnapshotTables(): array
	{
		$existing = self::getExistingTables();
		$excluded = array_fill_keys(self::EXCLUDED_OPERATIONAL_TABLES, true);
		$tables = [];

		foreach (self::PREFERRED_TABLE_ORDER as $table) {
			if (isset($existing[$table])) {
				$tables[] = $table;
			}
		}

		$selected = array_fill_keys($tables, true);
		$remaining = [];

		foreach (array_keys($existing) as $table) {
			if (!isset($excluded[$table]) && !isset($selected[$table])) {
				$remaining[] = $table;
			}
		}

		sort($remaining, SORT_STRING);

		return [...$tables, ...$remaining];
	}

	/**
	 * @return array<string, true>
	 */
	private static function getExistingTables(): array
	{
		$stmt = Db::instance()->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
		$tables = [];

		foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {
			$tables[(string) $row[0]] = true;
		}

		return $tables;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function getTableColumns(string $table): array
	{
		$stmt = Db::instance()->query('SHOW FULL COLUMNS FROM ' . self::quoteIdentifier($table));
		$columns = [];

		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
			$columns[] = [
				'field' => (string) $column['Field'],
				'type' => (string) $column['Type'],
				'collation' => $column['Collation'],
				'null' => (string) $column['Null'],
				'key' => (string) $column['Key'],
				'default' => $column['Default'],
				'extra' => (string) $column['Extra'],
				'comment' => (string) $column['Comment'],
			];
		}

		return $columns;
	}

	/**
	 * @return list<string>
	 */
	private static function getPrimaryKeyColumns(string $table): array
	{
		$columns = [];

		foreach (self::getTableColumns($table) as $column) {
			if (($column['key'] ?? '') === 'PRI') {
				$columns[] = (string) $column['field'];
			}
		}

		return $columns;
	}

	private static function getAutoIncrementColumn(string $table): ?string
	{
		foreach (self::getTableColumns($table) as $column) {
			if (str_contains((string) ($column['extra'] ?? ''), 'auto_increment')) {
				return (string) $column['field'];
			}
		}

		return null;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function fetchTableRows(string $table): array
	{
		$order_columns = self::getPrimaryKeyColumns($table);
		$order = $order_columns === []
			? ''
			: ' ORDER BY ' . implode(', ', array_map(self::quoteIdentifier(...), $order_columns));
		$stmt = Db::instance()->query('SELECT * FROM ' . self::quoteIdentifier($table) . $order);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * @param list<array<string, mixed>> $columns
	 * @param list<array<string, mixed>> $rows
	 */
	private static function insertTableRows(string $table, array $columns, array $rows): void
	{
		if ($rows === []) {
			return;
		}

		$column_names = array_map(
			static fn (array $column): string => (string) $column['field'],
			$columns
		);
		$sql = 'INSERT INTO ' . self::quoteIdentifier($table)
			. ' (' . implode(', ', array_map(self::quoteIdentifier(...), $column_names)) . ') VALUES ('
			. implode(', ', array_fill(0, count($column_names), '?')) . ')';
		$stmt = Db::instance()->prepare($sql);

		foreach ($rows as $row) {
			$values = [];

			foreach ($column_names as $column_name) {
				$values[] = $row[$column_name] ?? null;
			}

			$stmt->execute($values);
		}
	}

	/**
	 * @param list<array<string, mixed>> $columns
	 */
	private static function resetAutoIncrement(string $table, array $columns): void
	{
		$auto_increment_column = null;

		foreach ($columns as $column) {
			if (str_contains((string) ($column['extra'] ?? ''), 'auto_increment')) {
				$auto_increment_column = (string) $column['field'];

				break;
			}
		}

		if ($auto_increment_column === null) {
			return;
		}

		$stmt = Db::instance()->query(
			'SELECT MAX(' . self::quoteIdentifier($auto_increment_column) . ') FROM ' . self::quoteIdentifier($table)
		);
		$max = $stmt->fetchColumn();
		$next = is_numeric($max) ? ((int) $max + 1) : 1;

		Db::instance()->exec('ALTER TABLE ' . self::quoteIdentifier($table) . ' AUTO_INCREMENT = ' . max(1, $next));
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function buildUploadManifestFromDatabase(): array
	{
		$existing = self::getExistingTables();

		if (!isset($existing['mediacontainer_vfs_files'])) {
			return [];
		}

		$rows = self::fetchTableRows('mediacontainer_vfs_files');
		$manifest = [];

		foreach ($rows as $row) {
			$manifest[] = self::normalizeUploadManifestRow($row);
		}

		return $manifest;
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @return list<array<string, mixed>>
	 */
	private static function extractUploadManifestFromSnapshot(array $snapshot): array
	{
		$files = self::getRawUploadManifestRowsFromSnapshot($snapshot);

		return array_map(
			static fn (array $row): array => self::normalizeUploadManifestRow($row),
			$files
		);
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private static function normalizeUploadManifestRow(array $row): array
	{
		$storage_folder_id = (int) ($row['storage_folder_id'] ?? 0);
		$md5_hash = (string) ($row['md5_hash'] ?? '');

		return [
			'file_id' => (int) ($row['file_id'] ?? 0),
			'storage_folder_id' => $storage_folder_id,
			'md5_hash' => $md5_hash,
			'filesize' => (int) ($row['filesize'] ?? 0),
			'expected_relative_path' => self::getUploadDirectoryRelativePath() . '/' . $storage_folder_id . '/' . $md5_hash,
		];
	}

	/**
	 * @param array<string, mixed> $snapshot
	 */
	private static function validateSnapshot(array $snapshot): void
	{
		if (($snapshot['format'] ?? '') !== self::FORMAT) {
			throw new InvalidArgumentException('Invalid site snapshot format.');
		}

		if ((int) ($snapshot['version'] ?? 0) !== self::VERSION) {
			throw new InvalidArgumentException('Unsupported site snapshot version.');
		}

		if (!isset($snapshot['tables']) || !is_array($snapshot['tables'])) {
			throw new InvalidArgumentException('Site snapshot is missing tables.');
		}

		if (!isset($snapshot['schema']['signature']) || !is_string($snapshot['schema']['signature'])) {
			throw new InvalidArgumentException('Site snapshot is missing schema signature.');
		}

		self::validateSnapshotTableRows($snapshot);
		self::getRawUploadManifestRowsFromSnapshot($snapshot);

		$existing = self::getExistingTables();

		foreach (array_keys($snapshot['tables']) as $table) {
			if (!isset($existing[$table])) {
				throw new InvalidArgumentException("Snapshot table '{$table}' does not exist in the current database.");
			}
		}
	}

	/**
	 * @param array<string, mixed> $snapshot
	 */
	private static function validateSnapshotTableRows(array $snapshot): void
	{
		foreach ($snapshot['tables'] as $table => $rows) {
			if (!is_string($table) || $table === '') {
				throw new InvalidArgumentException('Snapshot table names must be non-empty strings.');
			}

			self::quoteIdentifier($table);

			if (!is_array($rows)) {
				throw new InvalidArgumentException("Snapshot table '{$table}' rows must be an array.");
			}

			if (!array_is_list($rows)) {
				throw new InvalidArgumentException("Snapshot table '{$table}' rows must be a list.");
			}

			foreach ($rows as $index => $row) {
				if (!is_array($row)) {
					throw new InvalidArgumentException("Snapshot table '{$table}' row #{$index} must be an object.");
				}

				foreach (array_keys($row) as $column) {
					if (!is_string($column)) {
						throw new InvalidArgumentException("Snapshot table '{$table}' row #{$index} column names must be strings.");
					}
				}
			}
		}
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @return list<array<string, mixed>>
	 */
	private static function getRawUploadManifestRowsFromSnapshot(array $snapshot): array
	{
		if (!isset($snapshot['uploads']) || !is_array($snapshot['uploads'])) {
			throw new InvalidArgumentException('Site snapshot is missing uploads manifest.');
		}

		if (!array_key_exists('files', $snapshot['uploads']) || !is_array($snapshot['uploads']['files'])) {
			throw new InvalidArgumentException('Snapshot uploads.files must be an array.');
		}

		$files = [];

		foreach ($snapshot['uploads']['files'] as $index => $file) {
			if (!is_array($file)) {
				throw new InvalidArgumentException("Snapshot uploads.files row #{$index} must be an object.");
			}

			foreach (['storage_folder_id', 'md5_hash', 'filesize'] as $required_field) {
				if (!array_key_exists($required_field, $file)) {
					throw new InvalidArgumentException("Snapshot uploads.files row #{$index} is missing {$required_field}.");
				}
			}

			/** @var array<string, mixed> $file */
			$files[] = $file;
		}

		return $files;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function checkSnapshotTrees(): array
	{
		$reports = [];
		$existing = self::getExistingTables();

		foreach (NestedSet::getTreeTableChoices() as $tree_key => $table) {
			if (isset($existing[$table])) {
				$reports[$tree_key] = NestedSet::analyzeConsistency($table);
			}
		}

		return $reports;
	}

	private static function getUploadDirectoryPath(): string
	{
		return rtrim(DEPLOY_ROOT . Config::PATH_UPLOADED_FILES_DIRECTORY->value(), '/');
	}

	private static function getUploadDirectoryRelativePath(): string
	{
		return trim(Config::PATH_UPLOADED_FILES_DIRECTORY->value(), '/');
	}

	private static function resolveDeployPath(string $path): string
	{
		$path = trim($path);

		if ($path === '') {
			throw new InvalidArgumentException('Path must not be empty.');
		}

		return str_starts_with($path, '/') ? $path : DEPLOY_ROOT . $path;
	}

	private static function quoteIdentifier(string $identifier): string
	{
		if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
			throw new InvalidArgumentException("Invalid SQL identifier: {$identifier}");
		}

		return '`' . $identifier . '`';
	}
}
