<?php

declare(strict_types=1);

class CmsSiteSnapshotService
{
	public const string FORMAT = 'radaptor.site_snapshot';
	public const int VERSION = 1;
	public const string PROFILE_DISASTER_RECOVERY = 'disaster_recovery';
	public const string PROFILE_SITE_MIGRATION = 'site_migration';
	private const string TABLE_COMMENT_NOEXPORT = '__noexport';
	private const array EXPECTED_EXPORT_COMMENT_TOKENS = [
		'runtime_worker_instances' => ['__noexport'],
		'runtime_worker_pause_requests' => ['__noexport'],
		'runtime_site_locks' => ['__noexport'],
		'mcp_tokens' => ['__noexport'],
		'mcp_audit' => ['__noexport'],
		'i18n_build_state' => ['__noexport'],
		'i18n_tm_entries' => ['__noexport'],
		'email_queue_transactional' => ['__noexport:disaster_recovery'],
		'email_queue_bulk' => ['__noexport:disaster_recovery'],
		'queued_jobs' => ['__noexport:disaster_recovery'],
		'email_queue_archive' => ['__noexport:disaster_recovery'],
		'email_queue_dead_letter' => ['__noexport:disaster_recovery'],
		'queued_jobs_archive' => ['__noexport:disaster_recovery'],
		'queued_jobs_dead_letter' => ['__noexport:disaster_recovery'],
		'email_outbox' => ['__noexport:disaster_recovery'],
		'email_outbox_recipients' => ['__noexport:disaster_recovery'],
		'email_attachments' => ['__noexport:disaster_recovery'],
		'email_outbox_attachments' => ['__noexport:disaster_recovery'],
	];

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

	/**
	 * @return array<string, mixed>
	 */
	public static function exportSnapshot(
		bool $uploads_backed_up,
		string $profile = self::PROFILE_DISASTER_RECOVERY,
		array $options = []
	): array {
		if (!$uploads_backed_up) {
			throw new InvalidArgumentException('Uploaded files must be backed up before exporting a site snapshot. Re-run with --uploads-backed-up after copying the upload directory.');
		}

		$profile = self::normalizeProfile($profile);
		$source_cutover_report = self::prepareExportSourceCutover($profile, $options);
		$worker_pause_report = self::prepareExportWorkerPause($profile, $options, $source_cutover_report);

		if (($source_cutover_report['required'] ?? false) === true && ($source_cutover_report['active'] ?? false) !== true) {
			throw new RuntimeException(t('import_export.error.source_cutover_required'));
		}

		if (($worker_pause_report['required'] ?? false) === true && ($worker_pause_report['confirmed'] ?? false) !== true) {
			throw new RuntimeException('Source email queue workers did not confirm pause before site migration export.');
		}

		$export_data = self::readExportSnapshotFromDatabase($profile);
		$uploads_report = self::checkUploadManifest($export_data['upload_manifest']);

		if (!$uploads_report['ok']) {
			throw new RuntimeException('Uploaded file consistency check failed. Fix or back up the upload directory before exporting the snapshot.');
		}

		return [
			'format' => self::FORMAT,
			'version' => self::VERSION,
			'profile' => $profile,
			'created_at' => gmdate(DATE_ATOM),
			'environment' => self::buildCurrentEnvironmentMetadata(),
			'app' => [
				'domain_context' => Config::APP_DOMAIN_CONTEXT->value(),
				'site_context' => class_exists(CmsSiteContext::class)
					? CmsSiteContext::getConfiguredSiteKey()
					: Config::APP_DOMAIN_CONTEXT->value(),
			],
			'schema' => $export_data['schema'],
			'table_counts' => $export_data['table_counts'],
			'tables' => $export_data['table_rows'],
			'excluded_tables' => $export_data['excluded_tables'],
			'source_cutover_lock' => $source_cutover_report,
			'worker_pause' => $worker_pause_report,
			'uploads' => [
				'directory' => self::getUploadDirectoryRelativePath(),
				'backed_up_confirmed' => true,
				'files' => $export_data['upload_manifest'],
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function writeSnapshot(
		string $output_path,
		bool $uploads_backed_up,
		string $profile = self::PROFILE_DISASTER_RECOVERY,
		array $options = []
	): array {
		$snapshot = self::exportSnapshot($uploads_backed_up, $profile, $options);
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
			'excluded_table_count' => count($snapshot['excluded_tables'] ?? []),
			'profile' => $snapshot['profile'] ?? self::PROFILE_DISASTER_RECOVERY,
			'source_cutover_lock' => $snapshot['source_cutover_lock'] ?? null,
			'worker_pause' => $snapshot['worker_pause'] ?? null,
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
	public static function importSnapshot(
		array $snapshot,
		bool $dry_run,
		bool $replace,
		bool $allow_environment_mismatch = false,
		array $options = []
	): array {
		self::validateSnapshot($snapshot);

		$tables = array_keys($snapshot['tables']);
		$profile = self::normalizeProfile((string) ($snapshot['profile'] ?? self::PROFILE_DISASTER_RECOVERY));
		$current_schema = self::buildSchema($tables);
		$environment_check = self::checkSnapshotEnvironment($snapshot, $allow_environment_mismatch);
		$errors = [];
		$worker_pause_report = self::buildImportWorkerPausePreview($profile, $options);

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

		if (!$dry_run && !$allow_environment_mismatch && ($environment_check['match'] ?? false) !== true) {
			$errors[] = 'Snapshot environment does not match the current environment. Re-run with --allow-environment-mismatch only if this restore target is intentional.';
		}

		$summary = self::buildImportSummary($snapshot);

		if (!$dry_run && $errors === []) {
			$worker_pause_report = self::prepareImportWorkerPause($profile, $options);

			if (($worker_pause_report['required'] ?? false) === true && ($worker_pause_report['confirmed'] ?? false) !== true) {
				$errors[] = 'Target email queue workers did not confirm pause before site migration restore.';
			}
		}

		if ($dry_run || $errors !== []) {
			return [
				'status' => $errors === [] ? 'success' : 'error',
				'dry_run' => $dry_run,
				'applied' => false,
				'profile' => $profile,
				'errors' => $errors,
				'summary' => $summary,
				'uploads' => self::summarizeUploadsReport($uploads_report),
				'environment_check' => $environment_check,
				'worker_pause' => $worker_pause_report,
			];
		}

		if (class_exists(CmsMutationAuditService::class)) {
			CmsMutationAuditService::recordLeaf('site.import.replace_tables', [
				'affected_count' => array_sum($summary),
				'summary' => [
					'tables' => count($summary),
					'rows' => array_sum($summary),
				],
			]);
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
			'profile' => $profile,
			'errors' => $errors,
			'summary' => $summary,
			'uploads' => self::summarizeUploadsReport(self::checkUploads($snapshot)),
			'environment_check' => $environment_check,
			'worker_pause' => $worker_pause_report,
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

		return self::checkUploadManifest($manifest);
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|null
	 */
	private static function prepareExportSourceCutover(string $profile, array $options): ?array
	{
		if (self::normalizeProfile($profile) !== self::PROFILE_SITE_MIGRATION) {
			return null;
		}

		if (!class_exists(RuntimeSiteCutoverGuard::class)) {
			return [
				'required' => true,
				'requested' => false,
				'active' => false,
				'available' => false,
			];
		}

		return RuntimeSiteCutoverGuard::activateSourceCutover(
			'site_migration_export',
			(string) ($options['pause_context'] ?? 'site_snapshot_export'),
			['source' => self::FORMAT]
		);
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|null
	 */
	private static function prepareExportWorkerPause(string $profile, array $options, ?array $source_cutover_report = null): ?array
	{
		if (self::normalizeProfile($profile) !== self::PROFILE_SITE_MIGRATION) {
			return null;
		}

		if (!self::optionBool($options, 'pause_source_workers', false)) {
			return [
				'required' => false,
				'requested' => false,
				'confirmed' => true,
				'skipped' => true,
				'scope' => self::emailQueueWorkerScope(),
			];
		}

		$metadata = [
			'source' => self::FORMAT,
		];
		$cutover_lock_id = self::extractCutoverLockId($source_cutover_report);

		if ($cutover_lock_id !== '') {
			$metadata['cutover_lock_id'] = $cutover_lock_id;
		}

		$result = self::requestAndWaitForEmailWorkerPause('site_migration_export', (string) ($options['pause_context'] ?? 'site_snapshot_export'), $options, $metadata);
		$pause_request_id = (string) ($result['request']['pause_request_id'] ?? '');
		$request_status = (string) ($result['request']['status'] ?? '');

		if (
			$cutover_lock_id !== ''
			&& $pause_request_id !== ''
			&& class_exists(RuntimeWorkerPauseControl::class)
			&& $request_status === RuntimeWorkerPauseControl::STATUS_REQUESTED
			&& class_exists(RuntimeSiteCutoverGuard::class)
		) {
			$result['cutover_worker_pause_link'] = RuntimeSiteCutoverGuard::attachWorkerPauseRequest($cutover_lock_id, $pause_request_id);
		}

		return $result;
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|null
	 */
	private static function buildImportWorkerPausePreview(string $profile, array $options): ?array
	{
		if (self::normalizeProfile($profile) !== self::PROFILE_SITE_MIGRATION) {
			return null;
		}

		return [
			'required' => true,
			'requested' => false,
			'confirmed' => false,
			'will_pause_target_workers' => self::optionBool($options, 'pause_target_workers', true),
			'scope' => self::emailQueueWorkerScope(),
		];
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|null
	 */
	private static function prepareImportWorkerPause(string $profile, array $options): ?array
	{
		if (self::normalizeProfile($profile) !== self::PROFILE_SITE_MIGRATION) {
			return null;
		}

		if (!self::optionBool($options, 'pause_target_workers', true)) {
			return [
				'required' => false,
				'requested' => false,
				'confirmed' => true,
				'skipped' => true,
				'scope' => self::emailQueueWorkerScope(),
			];
		}

		return self::requestAndWaitForEmailWorkerPause('site_migration_restore', (string) ($options['pause_context'] ?? 'site_snapshot_restore'), $options);
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	private static function requestAndWaitForEmailWorkerPause(string $reason, string $context, array $options, array $metadata = []): array
	{
		$scope = self::emailQueueWorkerScope();

		if (!class_exists(RuntimeWorkerPauseControl::class) || !class_exists(EmailQueueWorker::class)) {
			return [
				'required' => true,
				'requested' => false,
				'confirmed' => false,
				'available' => false,
				'scope' => $scope,
			];
		}

		$request = RuntimeWorkerPauseControl::requestPause(
			EmailQueueWorker::WORKER_TYPE,
			EmailQueueWorker::QUEUE_NAME,
			$reason,
			$context,
			$metadata === [] ? ['source' => self::FORMAT] : $metadata
		);
		$pause_request_id = $request['pause_request_id'] ?? null;
		$confirmation = null;

		if (is_string($pause_request_id) && $pause_request_id !== '') {
			$confirmation = RuntimeWorkerPauseControl::waitForPauseConfirmation(
				EmailQueueWorker::WORKER_TYPE,
				EmailQueueWorker::QUEUE_NAME,
				$pause_request_id,
				self::optionInt($options, 'pause_timeout_seconds', 30),
				self::optionBool($options, 'allow_stale_workers', false)
			);
		}

		return [
			'required' => true,
			'requested' => is_string($pause_request_id) && $pause_request_id !== '',
			'confirmed' => (bool) ($confirmation['confirmed'] ?? false),
			'skipped' => false,
			'scope' => $scope,
			'request' => $request,
			'confirmation' => $confirmation,
		];
	}

	/**
	 * @param array<string, mixed>|null $source_cutover_report
	 */
	private static function extractCutoverLockId(?array $source_cutover_report): string
	{
		if (!is_array($source_cutover_report)) {
			return '';
		}

		$lock = $source_cutover_report['lock'] ?? null;

		if (!is_array($lock)) {
			return '';
		}

		return (string) ($lock['lock_id'] ?? '');
	}

	/**
	 * @return array{worker_type: string, queue_name: string}
	 */
	private static function emailQueueWorkerScope(): array
	{
		return [
			'worker_type' => class_exists(EmailQueueWorker::class) ? EmailQueueWorker::WORKER_TYPE : 'email_queue',
			'queue_name' => class_exists(EmailQueueWorker::class) ? EmailQueueWorker::QUEUE_NAME : 'transactional_email',
		];
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private static function optionBool(array $options, string $key, bool $default): bool
	{
		if (!array_key_exists($key, $options)) {
			return $default;
		}

		$value = $options[$key];

		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value)) {
			return $value === 1;
		}

		if (is_string($value)) {
			return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
		}

		return $default;
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private static function optionInt(array $options, string $key, int $default): int
	{
		$value = $options[$key] ?? null;

		return is_numeric($value) ? (int) $value : $default;
	}

	/**
	 * @param list<array<string, mixed>> $manifest
	 * @return array<string, mixed>
	 */
	private static function checkUploadManifest(array $manifest): array
	{
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
	private static function buildCurrentEnvironmentMetadata(): array
	{
		$resolved_site_context = null;
		$site_context_error = null;

		if (class_exists(CmsSiteContext::class)) {
			try {
				$resolved_site_context = CmsSiteContext::resolve();
			} catch (Throwable $exception) {
				$site_context_error = $exception->getMessage();
			}
		}

		$metadata = [
			'environment' => Kernel::getEnvironment(),
			'application_identifier' => Config::APP_APPLICATION_IDENTIFIER->value(),
			'domain_context' => Config::APP_DOMAIN_CONTEXT->value(),
			'configured_site_context' => class_exists(CmsSiteContext::class)
				? CmsSiteContext::getConfiguredSiteKey()
				: Config::APP_DOMAIN_CONTEXT->value(),
			'resolved_site_context' => $resolved_site_context,
			'database' => self::parseDsnForEnvironmentMetadata(Db::normalizeDsn((string) Config::DB_DEFAULT_DSN->value())),
		];

		if ($site_context_error !== null) {
			$metadata['site_context_error'] = $site_context_error;
		}

		return $metadata;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function parseDsnForEnvironmentMetadata(string $dsn): array
	{
		$driver = '';
		$payload = $dsn;
		$separator = strpos($dsn, ':');

		if ($separator !== false) {
			$driver = substr($dsn, 0, $separator);
			$payload = substr($dsn, $separator + 1);
		}

		$parts = [
			'driver' => $driver,
		];

		foreach (explode(';', $payload) as $part) {
			$part = trim($part);

			if ($part === '' || !str_contains($part, '=')) {
				continue;
			}

			[$key, $value] = explode('=', $part, 2);
			$key = strtolower(trim($key));

			if (!in_array($key, ['host', 'port', 'dbname', 'unix_socket'], true)) {
				continue;
			}

			$parts[$key] = trim($value);
		}

		return $parts;
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @return array<string, mixed>
	 */
	private static function checkSnapshotEnvironment(array $snapshot, bool $allow_environment_mismatch): array
	{
		$current = self::buildCurrentEnvironmentMetadata();
		$snapshot_environment = $snapshot['environment'] ?? null;

		if (!is_array($snapshot_environment)) {
			return [
				'status' => 'legacy_missing',
				'match' => true,
				'allowed' => true,
				'legacy' => true,
				'snapshot' => null,
				'current' => $current,
				'differences' => [],
			];
		}

		$compared_paths = [
			'environment',
			'domain_context',
			'configured_site_context',
			'resolved_site_context',
			'database.driver',
			'database.host',
			'database.port',
			'database.dbname',
			'database.unix_socket',
		];
		$differences = [];

		foreach ($compared_paths as $path) {
			$snapshot_value = self::getArrayPath($snapshot_environment, $path);
			$current_value = self::getArrayPath($current, $path);

			if ($snapshot_value === $current_value) {
				continue;
			}

			$differences[] = [
				'path' => $path,
				'snapshot' => $snapshot_value,
				'current' => $current_value,
			];
		}

		return [
			'status' => $differences === [] ? 'match' : 'mismatch',
			'match' => $differences === [],
			'allowed' => $allow_environment_mismatch,
			'snapshot' => $snapshot_environment,
			'current' => $current,
			'differences' => $differences,
		];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function getArrayPath(array $data, string $path): mixed
	{
		$current = $data;

		foreach (explode('.', $path) as $part) {
			if (!is_array($current) || !array_key_exists($part, $current)) {
				return null;
			}

			$current = $current[$part];
		}

		return $current;
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
	 * @return array{
	 *     schema: array<string, mixed>,
	 *     table_rows: array<string, list<array<string, mixed>>>,
	 *     table_counts: array<string, int>,
	 *     upload_manifest: list<array<string, mixed>>,
	 *     excluded_tables: array<string, array<string, mixed>>
	 * }
	 */
	private static function readExportSnapshotFromDatabase(string $profile): array
	{
		$pdo = Db::instance();
		$transaction_started = false;

		try {
			if (!$pdo->inTransaction()) {
				$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
				$pdo->exec('START TRANSACTION READ ONLY');
				$transaction_started = true;
			}

			$table_selection = self::getSnapshotTableSelection($profile);
			self::assertExpectedExportCommentTokens($table_selection['missing_expected_comments']);
			$tables = $table_selection['tables'];
			$schema = self::buildSchema($tables);
			$table_rows = [];
			$table_counts = [];

			foreach ($tables as $table) {
				$rows = self::fetchTableRows($table);
				$table_rows[$table] = $rows;
				$table_counts[$table] = count($rows);
			}

			$upload_rows = $table_rows['mediacontainer_vfs_files'] ?? [];
			$upload_manifest = self::buildUploadManifestFromRows($upload_rows);

			if ($transaction_started) {
				$pdo->commit();
			}

			return [
				'schema' => $schema,
				'table_rows' => $table_rows,
				'table_counts' => $table_counts,
				'upload_manifest' => $upload_manifest,
				'excluded_tables' => $table_selection['excluded_tables'],
			];
		} catch (Throwable $exception) {
			if ($transaction_started && $pdo->inTransaction()) {
				$pdo->rollBack();
			}

			throw $exception;
		}
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
	private static function getSnapshotTables(string $profile = self::PROFILE_DISASTER_RECOVERY): array
	{
		return self::getSnapshotTableSelection($profile)['tables'];
	}

	/**
	 * @return array{
	 *     tables: list<string>,
	 *     excluded_tables: array<string, array{reason: string, comment: string, tokens: list<string>}>,
	 *     missing_expected_comments: list<array{table: string, token: string, comment: string}>
	 * }
	 */
	private static function getSnapshotTableSelection(string $profile): array
	{
		$profile = self::normalizeProfile($profile);
		$existing = self::getExistingTables();
		$comments = self::getExistingTableComments();
		$tables = [];
		$excluded_tables = [];

		foreach ($comments as $table => $comment) {
			$exclude_reason = self::getTableExcludeReasonForProfile($comment, $profile);

			if ($exclude_reason === null) {
				continue;
			}

			$excluded_tables[$table] = [
				'reason' => $exclude_reason,
				'comment' => $comment,
				'tokens' => self::parseTableCommentTokens($comment),
			];
		}

		$missing_expected_comments = self::getMissingExpectedExportCommentTokens($existing, $comments, $profile);

		foreach (self::PREFERRED_TABLE_ORDER as $table) {
			if (isset($existing[$table]) && !isset($excluded_tables[$table])) {
				$tables[] = $table;
			}
		}

		$selected = array_fill_keys($tables, true);
		$remaining = [];

		foreach (array_keys($existing) as $table) {
			if (!isset($excluded_tables[$table]) && !isset($selected[$table])) {
				$remaining[] = $table;
			}
		}

		sort($remaining, SORT_STRING);
		ksort($excluded_tables, SORT_STRING);

		return [
			'tables' => [...$tables, ...$remaining],
			'excluded_tables' => $excluded_tables,
			'missing_expected_comments' => $missing_expected_comments,
		];
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
	 * @return array<string, string>
	 */
	private static function getExistingTableComments(): array
	{
		$stmt = Db::instance()->query(
			"SELECT TABLE_NAME, TABLE_COMMENT
			 FROM information_schema.TABLES
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_TYPE = 'BASE TABLE'"
		);
		$comments = [];

		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$comments[(string) $row['TABLE_NAME']] = (string) ($row['TABLE_COMMENT'] ?? '');
		}

		return $comments;
	}

	/**
	 * @return list<string>
	 */
	private static function parseTableCommentTokens(string $comment): array
	{
		$tokens = [];

		foreach (explode(',', $comment) as $token) {
			$token = strtolower(trim($token));

			if ($token === '') {
				continue;
			}

			$tokens[$token] = true;
		}

		return array_keys($tokens);
	}

	private static function normalizeProfile(string $profile): string
	{
		$profile = strtolower(trim($profile));

		return in_array($profile, [self::PROFILE_DISASTER_RECOVERY, self::PROFILE_SITE_MIGRATION], true)
			? $profile
			: self::PROFILE_DISASTER_RECOVERY;
	}

	private static function getTableExcludeReasonForProfile(string $comment, string $profile): ?string
	{
		if (self::tableCommentHasToken($comment, self::TABLE_COMMENT_NOEXPORT)) {
			return self::TABLE_COMMENT_NOEXPORT;
		}

		$profile_token = self::TABLE_COMMENT_NOEXPORT . ':' . self::normalizeProfile($profile);

		if (self::tableCommentHasToken($comment, $profile_token)) {
			return $profile_token;
		}

		return null;
	}

	private static function tableCommentHasToken(string $comment, string $token): bool
	{
		return in_array(strtolower(trim($token)), self::parseTableCommentTokens($comment), true);
	}

	/**
	 * @param array<string, true> $existing
	 * @param array<string, string> $comments
	 * @return list<array{table: string, token: string, comment: string}>
	 */
	private static function getMissingExpectedExportCommentTokens(array $existing, array $comments, string $profile): array
	{
		$missing = [];
		$profile = self::normalizeProfile($profile);

		foreach (self::EXPECTED_EXPORT_COMMENT_TOKENS as $table => $tokens) {
			if (!isset($existing[$table])) {
				continue;
			}

			$comment = $comments[$table] ?? '';

			foreach ($tokens as $token) {
				if (str_starts_with($token, self::TABLE_COMMENT_NOEXPORT . ':')) {
					[, $token_profile] = explode(':', $token, 2);

					if ($token_profile !== $profile) {
						continue;
					}
				}

				if (!self::tableCommentHasToken($comment, $token)) {
					$missing[] = [
						'table' => $table,
						'token' => $token,
						'comment' => $comment,
					];
				}
			}
		}

		return $missing;
	}

	/**
	 * @param list<array{table: string, token: string, comment: string}> $missing_expected_comments
	 */
	private static function assertExpectedExportCommentTokens(array $missing_expected_comments): void
	{
		if ($missing_expected_comments === []) {
			return;
		}

		$items = array_map(
			static fn (array $item): string => $item['table'] . ' requires table comment token ' . $item['token'],
			$missing_expected_comments
		);

		throw new RuntimeException(
			'Site snapshot export found operational tables without explicit export-safety comments. '
			. 'Run pending migrations or add explicit __noexport table comments before exporting. '
			. implode('; ', $items)
		);
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

		return self::buildUploadManifestFromRows(self::fetchTableRows('mediacontainer_vfs_files'));
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return list<array<string, mixed>>
	 */
	private static function buildUploadManifestFromRows(array $rows): array
	{
		return array_map(
			static fn (array $row): array => self::normalizeUploadManifestRow($row),
			$rows
		);
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
