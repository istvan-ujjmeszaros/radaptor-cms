<?php

declare(strict_types=1);

class EventImportExportDownload extends AbstractEvent implements iBrowserEventDocumentable
{
	private ?AbstractImportExportDataset $_dataset = null;

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'import_export.download',
			'group' => 'Import / Export',
			'name' => 'Download dataset export',
			'summary' => 'Streams a CSV export for one import/export dataset.',
			'description' => 'Resolves a dataset by key, collects scalar GET options, and returns the dataset export as a CSV download.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('dataset', 'query', 'string', true, 'Dataset key to export.'),
					BrowserEventDocumentationHelper::param('*', 'query', 'string', false, 'Additional scalar dataset-specific export options.'),
				],
			],
			'response' => [
				'kind' => 'file-download',
				'content_type' => 'text/csv; charset=UTF-8',
				'description' => 'Returns the generated CSV stream with a download filename.',
			],
			'authorization' => [
				'visibility' => 'dataset-specific',
				'description' => 'Allowed only when the resolved dataset exists and supports export.',
			],
			'notes' => BrowserEventDocumentationHelper::lines(
				'The available option set depends on the selected dataset implementation.'
			),
			'side_effects' => [],
		];
	}

	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		$this->_dataset = $this->_resolveDatasetFromGet();

		return $this->_dataset !== null && $this->_dataset->supportsExport()
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$dataset = $this->_dataset ?? $this->_resolveDatasetFromGet();

		if ($dataset === null || !$dataset->supportsExport()) {
			Kernel::abort(t('import_export.error.dataset_not_found'));
		}

		$options = $this->_collectStringOptions(RequestContextHolder::current()->GET);
		$csv = $dataset->export($options);
		$filename = $dataset->buildExportFilename($options);

		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		echo $csv;
	}

	private function _resolveDatasetFromGet(): ?AbstractImportExportDataset
	{
		$key = trim(Request::_GET('dataset', ''));

		if ($key === '') {
			return null;
		}

		return ImportExportDataset::getByKey($key);
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array<string, string>
	 */
	private function _collectStringOptions(array $raw): array
	{
		$options = [];

		foreach ($raw as $key => $value) {
			if (is_scalar($value) || $value === null) {
				$options[(string) $key] = trim((string) $value);
			}
		}

		return $options;
	}
}
