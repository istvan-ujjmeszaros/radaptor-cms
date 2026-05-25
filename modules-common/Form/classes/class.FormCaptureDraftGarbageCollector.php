<?php

declare(strict_types=1);

final class FormCaptureDraftGarbageCollector
{
	/**
	 * @return array{
	 *     status: string,
	 *     dry_run: bool,
	 *     older_than_days: int,
	 *     cutoff: string,
	 *     matched_rows: int,
	 *     deleted_rows: int,
	 *     errors: list<array{version_id: int, message: string}>,
	 *     versions: list<array{version_id: int, definition_slug: string, version_number: int, created_at: string}>
	 * }
	 */
	public function run(int $older_than_days = 30, bool $dry_run = true): array
	{
		if ($older_than_days < 0) {
			throw new InvalidArgumentException('older-than-days must be zero or greater.');
		}

		$cutoff = date('Y-m-d H:i:s', time() - ($older_than_days * 86400));
		$versions = $this->candidates($cutoff);
		$deleted = 0;
		$errors = [];

		if (!$dry_run) {
			foreach ($versions as $version) {
				$version_id = (int)$version['version_id'];

				if (EntityFormDefinitionVersion::delete($version_id)) {
					$deleted++;

					continue;
				}

				$errors[] = [
					'version_id' => $version_id,
					'message' => 'Unable to delete abandoned capture form draft version.',
				];
			}
		}

		return [
			'status' => $errors === [] ? 'success' : 'partial',
			'dry_run' => $dry_run,
			'older_than_days' => $older_than_days,
			'cutoff' => $cutoff,
			'matched_rows' => count($versions),
			'deleted_rows' => $deleted,
			'errors' => $errors,
			'versions' => $versions,
		];
	}

	/**
	 * @return list<array{version_id: int, definition_slug: string, version_number: int, created_at: string}>
	 */
	private function candidates(string $cutoff): array
	{
		if (!$this->tableExists('form_definitions') || !$this->tableExists('form_definition_versions')) {
			return [];
		}

		$rows = DbHelper::selectManyFromQuery(
			"SELECT
				v.version_id,
				d.definition_slug,
				v.version_number,
				v.created_at
			FROM form_definition_versions v
			INNER JOIN form_definitions d
				ON d.definition_id = v.definition_id
			WHERE d.kind = 'capture'
			  AND v.status = 'abandoned'
			  AND v.created_at < ?
			  AND (d.published_version_id IS NULL OR d.published_version_id <> v.version_id)
			  AND NOT EXISTS (
			  	SELECT 1
			  	FROM form_submissions s
			  	WHERE s.version_id = v.version_id
			  )
			ORDER BY v.created_at, d.definition_slug, v.version_number",
			[$cutoff],
		);

		return array_map(static fn (array $row): array => [
			'version_id' => (int)$row['version_id'],
			'definition_slug' => (string)$row['definition_slug'],
			'version_number' => (int)$row['version_number'],
			'created_at' => (string)$row['created_at'],
		], $rows);
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
