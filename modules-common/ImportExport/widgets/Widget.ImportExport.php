<?php

declare(strict_types=1);

class WidgetImportExport extends AbstractWidget
{
	public const string ID = 'import_export';

	public static function getName(): string
	{
		return t('widget.' . self::ID . '.name');
	}

	public static function getDescription(): string
	{
		return t('widget.' . self::ID . '.description');
	}

	public static function getListVisibility(): bool
	{
		return Roles::hasRole(RoleList::ROLE_SYSTEM_ADMINISTRATOR);
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/import-export/',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		$datasets = ImportExportDataset::getVisibleDatasetList();
		$selectedKey = trim(Request::_GET('dataset', ''));
		$selectedDataset = null;

		if ($selectedKey !== '') {
			$selectedDataset = ImportExportDataset::getByKey($selectedKey);
		}

		if ($selectedDataset === null && !empty($datasets)) {
			$selectedDataset = $datasets[0];
			$selectedKey = $selectedDataset->getKey();
		}

		return $this->createComponentTree('importExport', [
			'datasets' => $datasets,
			'selected_dataset' => $selectedDataset,
			'selected_dataset_key' => $selectedKey,
			'current_url' => Url::getCurrentUrl(),
		]);
	}

	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return Roles::hasRole(RoleList::ROLE_SYSTEM_ADMINISTRATOR);
	}
}
