<?php

declare(strict_types=1);

final class FormCaptureCompiledDescriptorCacheGarbageCollector
{
	public function __construct(
		private readonly FormCaptureCompiledDescriptorCache $_cache = new FormCaptureCompiledDescriptorCache(),
	) {
	}

	/**
	 * @return array{
	 *     status: string,
	 *     dry_run: bool,
	 *     definition_slug: string|null,
	 *     matched_files: int,
	 *     kept_files: int,
	 *     delete_candidates: int,
	 *     deleted_files: int,
	 *     errors: list<array{path: string, reason: string, message?: string}>,
	 *     files: list<array{path: string, definition_slug: string|null, version_number: int|null, action: string, reason: string}>
	 * }
	 */
	public function run(bool $dry_run = true, ?string $definition_slug = null): array
	{
		$definition_slug = $definition_slug !== null && trim($definition_slug) !== '' ? trim($definition_slug) : null;
		$current = $this->currentPublishedBySlug($definition_slug);
		$files = [];
		$errors = [];
		$kept = 0;
		$candidates = 0;
		$deleted = 0;

		foreach ($this->_cache->listPaths($definition_slug) as $path) {
			$classification = $this->classifyPath($path, $current);
			$files[] = $classification;

			if ($classification['action'] === 'keep') {
				$kept++;

				continue;
			}

			if ($classification['action'] !== 'delete') {
				continue;
			}

			$candidates++;

			if ($dry_run) {
				continue;
			}

			if ($this->_cache->deletePath($path)) {
				$deleted++;

				continue;
			}

			$errors[] = [
				'path' => $path,
				'reason' => 'delete_failed',
				'message' => 'Unable to delete compiled form cache file.',
			];
		}

		return [
			'status' => $errors === [] ? 'success' : 'partial',
			'dry_run' => $dry_run,
			'definition_slug' => $definition_slug,
			'matched_files' => count($files),
			'kept_files' => $kept,
			'delete_candidates' => $candidates,
			'deleted_files' => $deleted,
			'errors' => $errors,
			'files' => $files,
		];
	}

	/**
	 * @param array<string, array{definition: array<string, mixed>, version: array<string, mixed>}> $current
	 * @return array{path: string, definition_slug: string|null, version_number: int|null, action: string, reason: string}
	 */
	private function classifyPath(string $path, array $current): array
	{
		$basename = basename($path);

		if (preg_match('/^([a-z0-9][a-z0-9_-]*)\.v([0-9]+)\.php$/D', $basename, $matches) !== 1) {
			return [
				'path' => $path,
				'definition_slug' => null,
				'version_number' => null,
				'action' => 'skip',
				'reason' => 'invalid_name',
			];
		}

		$definition_slug = $matches[1];
		$version_number = (int)$matches[2];
		$current_entry = $current[$definition_slug] ?? null;

		if ($current_entry === null) {
			return [
				'path' => $path,
				'definition_slug' => $definition_slug,
				'version_number' => $version_number,
				'action' => 'delete',
				'reason' => 'not_currently_published',
			];
		}

		$current_version_number = (int)($current_entry['version']['version_number'] ?? 0);

		if ($version_number !== $current_version_number) {
			return [
				'path' => $path,
				'definition_slug' => $definition_slug,
				'version_number' => $version_number,
				'action' => 'delete',
				'reason' => 'stale_version',
			];
		}

		if ($this->_cache->read($current_entry['definition'], $current_entry['version']) === null) {
			return [
				'path' => $path,
				'definition_slug' => $definition_slug,
				'version_number' => $version_number,
				'action' => 'delete',
				'reason' => 'current_metadata_mismatch_or_corrupt',
			];
		}

		return [
			'path' => $path,
			'definition_slug' => $definition_slug,
			'version_number' => $version_number,
			'action' => 'keep',
			'reason' => 'current_published',
		];
	}

	/**
	 * @return array<string, array{definition: array<string, mixed>, version: array<string, mixed>}>
	 */
	private function currentPublishedBySlug(?string $definition_slug): array
	{
		if (!$this->tableExists('form_definitions') || !$this->tableExists('form_definition_versions')) {
			return [];
		}

		$where = "WHERE d.kind = 'capture'
			  AND d.published_version_id IS NOT NULL";
		$params = [];

		if ($definition_slug !== null) {
			$where .= "\n			  AND d.definition_slug = ?";
			$params[] = $definition_slug;
		}

		$rows = DbHelper::selectManyFromQuery(
			"SELECT
				d.definition_id,
				d.definition_slug,
				d.kind,
				d.source,
				d.status AS definition_status,
				d.owner_user_id,
				d.security_json,
				d.published_version_id,
				v.version_id,
				v.version_number,
				v.status AS version_status,
				v.descriptor_hash,
				v.published_at
			FROM form_definitions d
			INNER JOIN form_definition_versions v
				ON v.version_id = d.published_version_id
			   AND v.definition_id = d.definition_id
			{$where}",
			$params,
		);
		$current = [];

		foreach ($rows as $row) {
			$slug = (string)$row['definition_slug'];
			$current[$slug] = [
				'definition' => [
					'definition_id' => (int)$row['definition_id'],
					'definition_slug' => $slug,
					'kind' => (string)$row['kind'],
					'source' => (string)$row['source'],
					'status' => (string)$row['definition_status'],
					'owner_user_id' => $row['owner_user_id'] === null ? null : (int)$row['owner_user_id'],
					'security_json' => (string)$row['security_json'],
					'published_version_id' => (int)$row['published_version_id'],
				],
				'version' => [
					'version_id' => (int)$row['version_id'],
					'definition_id' => (int)$row['definition_id'],
					'version_number' => (int)$row['version_number'],
					'status' => (string)$row['version_status'],
					'descriptor_hash' => (string)$row['descriptor_hash'],
					'published_at' => $row['published_at'] === null ? null : (string)$row['published_at'],
				],
			];
		}

		return $current;
	}

	private function tableExists(string $table): bool
	{
		$stmt = Db::instance()->prepare(
			"SELECT 1
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_TYPE = 'BASE TABLE'
			  AND TABLE_NAME = ?"
		);
		$stmt->execute([$table]);

		return (bool)$stmt->fetchColumn();
	}
}
