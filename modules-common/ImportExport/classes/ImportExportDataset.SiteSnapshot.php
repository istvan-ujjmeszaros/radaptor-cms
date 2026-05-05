<?php

declare(strict_types=1);

class ImportExportDatasetSiteSnapshot extends AbstractImportExportDataset
{
	public const string ID = 'site_snapshot';

	public function getName(): string
	{
		return t('import_export.dataset.site_snapshot.name');
	}

	public function getDescription(): string
	{
		return t('import_export.dataset.site_snapshot.description');
	}

	public function getListVisibility(): bool
	{
		return Roles::hasRole(RoleList::ROLE_SYSTEM_ADMINISTRATOR);
	}

	public function getExportFieldDefinitions(): array
	{
		return [
			'uploads_backed_up' => [
				'type' => 'select',
				'label' => t('import_export.field.uploads_backed_up.label'),
				'required' => true,
				'default' => '0',
				'help' => t('import_export.field.uploads_backed_up.help'),
				'options' => [
					'0' => t('common.no'),
					'1' => t('common.yes'),
				],
			],
		];
	}

	public function getImportFieldDefinitions(): array
	{
		return [
			'csv_file' => [
				'type' => 'file',
				'label' => t('import_export.field.site_snapshot_file.label'),
				'required' => true,
				'accept' => '.json,application/json',
				'help' => t('import_export.field.site_snapshot_file.help'),
			],
			'replace_confirmed' => [
				'type' => 'select',
				'label' => t('import_export.field.replace_confirmed.label'),
				'required' => true,
				'default' => '0',
				'help' => t('import_export.field.replace_confirmed.help'),
				'options' => [
					'0' => t('common.no'),
					'1' => t('common.yes'),
				],
			],
		];
	}

	public function getExportContentType(): string
	{
		return 'application/json; charset=UTF-8';
	}

	public function getExportTitle(): string
	{
		return t('import_export.site_snapshot.export.title');
	}

	public function getImportTitle(): string
	{
		return t('import_export.site_snapshot.import.title');
	}

	public function getExportActionLabel(): string
	{
		return t('import_export.site_snapshot.action.export');
	}

	public function buildExportFilename(array $options): string
	{
		return 'site-snapshot-' . gmdate('Ymd-His') . '.json';
	}

	public function export(array $options): string
	{
		if (($options['uploads_backed_up'] ?? '') !== '1') {
			throw new InvalidArgumentException(t('import_export.error.upload_backup_required'));
		}

		$snapshot = CmsSiteSnapshotService::exportSnapshot(true);

		return CmsSiteSnapshotService::encodeSnapshot($snapshot);
	}

	public function import(string $csvContent, array $options): array
	{
		$dry_run = ($options['dry_run'] ?? '0') === '1';
		$replace_confirmed = ($options['replace_confirmed'] ?? '0') === '1';
		$snapshot = CmsSiteSnapshotService::decodeSnapshot($csvContent);
		$result = CmsSiteSnapshotService::importSnapshot($snapshot, $dry_run, $replace_confirmed);
		$processed = array_sum(array_map('intval', $result['summary'] ?? []));

		return [
			'processed' => $processed,
			'imported' => $result['applied'] ? $processed : 0,
			'inserted' => $result['applied'] ? $processed : 0,
			'updated' => 0,
			'skipped' => $result['applied'] ? 0 : $processed,
			'deleted' => 0,
			'errors' => $result['errors'] ?? [],
			'row_results' => self::buildRowResults($result),
			'format' => CmsSiteSnapshotService::FORMAT . '.v' . CmsSiteSnapshotService::VERSION,
			'mode' => $dry_run ? 'dry-run' : 'replace',
		];
	}

	/**
	 * @param array<string, mixed> $result
	 * @return list<array<string, mixed>>
	 */
	private static function buildRowResults(array $result): array
	{
		$row_results = [];

		foreach (($result['summary'] ?? []) as $table => $count) {
			$row_results[] = [
				'action' => $result['applied'] ? 'imported' : 'checked',
				'natural_key' => (string) $table,
				'reason' => (string) $count . ' row(s)',
			];
		}

		return $row_results;
	}
}
