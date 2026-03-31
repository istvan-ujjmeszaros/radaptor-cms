<?php

declare(strict_types=1);

class EventImportExportImport extends AbstractEvent
{
	private ?AbstractImportExportDataset $_dataset = null;

	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		$this->_dataset = $this->_resolveDatasetFromPost();

		return $this->_dataset !== null && $this->_dataset->supportsImport()
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$dataset = $this->_dataset ?? $this->_resolveDatasetFromPost();
		$referer = trim(Request::_POST('referer', Url::getCurrentUrl()));
		$ajax = $this->_wantsJsonResponse();

		if ($dataset === null || !$dataset->supportsImport()) {
			if ($ajax) {
				$this->_respondJson(404, [
					'ok' => false,
					'dry_run' => Request::_POST('dry_run', '0') === '1',
					'dataset_key' => '',
					'dataset_name' => '',
					'result' => null,
					'errors' => [t('import_export.error.dataset_not_found')],
				]);
			}

			SystemMessages::_error(t('import_export.error.dataset_not_found'));
			Url::redirect($referer);
		}

		$upload = $_FILES['csv_file'] ?? null;

		if (!is_array($upload) || empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
			if ($ajax) {
				$this->_respondJson(400, [
					'ok' => false,
					'dry_run' => Request::_POST('dry_run', '0') === '1',
					'dataset_key' => $dataset->getKey(),
					'dataset_name' => $dataset->getName(),
					'result' => null,
					'errors' => [t('import_export.error.file_required')],
				]);
			}

			SystemMessages::_error(t('import_export.error.file_required'));
			Url::redirect($referer);
		}

		$csvContent = file_get_contents($upload['tmp_name']);

		if ($csvContent === false) {
			if ($ajax) {
				$this->_respondJson(400, [
					'ok' => false,
					'dry_run' => Request::_POST('dry_run', '0') === '1',
					'dataset_key' => $dataset->getKey(),
					'dataset_name' => $dataset->getName(),
					'result' => null,
					'errors' => [t('import_export.error.file_read')],
				]);
			}

			SystemMessages::_error(t('import_export.error.file_read'));
			Url::redirect($referer);
		}

		$options = $this->_collectStringOptions(RequestContextHolder::current()->POST);
		$options['dry_run'] = Request::_POST('dry_run', '0');
		$dryRun = $options['dry_run'] === '1';

		try {
			$result = $dataset->import($csvContent, $options);

			if ($ajax) {
				$this->_respondJson(200, [
					'ok' => empty($result['errors']),
					'dry_run' => $dryRun,
					'dataset_key' => $dataset->getKey(),
					'dataset_name' => $dataset->getName(),
					'result' => $this->_buildAjaxResult($result),
					'errors' => $result['errors'],
				]);
			}

			$this->_reportResult($dataset, $result, $options['dry_run'] === '1');
		} catch (Throwable $e) {
			if ($ajax) {
				$this->_respondJson(422, [
					'ok' => false,
					'dry_run' => $dryRun,
					'dataset_key' => $dataset->getKey(),
					'dataset_name' => $dataset->getName(),
					'result' => null,
					'errors' => [$e->getMessage()],
				]);
			}

			SystemMessages::_error(nl2br(e($e->getMessage())));
		}

		Url::redirect($referer);
	}

	private function _resolveDatasetFromPost(): ?AbstractImportExportDataset
	{
		$key = trim(Request::_POST('dataset', ''));

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

	private function _wantsJsonResponse(): bool
	{
		if (Request::_POST('ajax', '0') === '1') {
			return true;
		}

		$server = RequestContextHolder::current()->SERVER;
		$requestedWith = strtolower(trim((string) ($server['HTTP_X_REQUESTED_WITH'] ?? '')));
		$accept = strtolower(trim((string) ($server['HTTP_ACCEPT'] ?? '')));

		return $requestedWith === 'xmlhttprequest' || str_contains($accept, 'application/json');
	}

	/**
	 * @param array<string, mixed> $result
	 * @return array<string, mixed>
	 */
	private function _buildAjaxResult(array $result): array
	{
		unset($result['row_results']);

		return $result;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function _respondJson(int $statusCode, array $payload): never
	{
		http_response_code($statusCode);
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		exit;
	}

	/**
	 * @param array{
	 *   processed: int,
	 *   imported: int,
	 *   inserted: int,
	 *   updated: int,
	 *   skipped: int,
	 *   deleted: int,
	 *   errors: list<string>,
	 *   row_results: list<array{
	 *     line?: int,
	 *     action: string,
	 *     natural_key?: string,
	 *     reason?: string
	 *   }>,
	 *   detected_locale?: string,
	 *   detected_locales?: list<string>,
	 *   mode?: string,
	 *   format?: string
	 * } $result
	 */
	private function _reportResult(AbstractImportExportDataset $dataset, array $result, bool $dryRun): void
	{
		$lines = [];

		if (!empty($result['detected_locales'])) {
			$label = count($result['detected_locales']) === 1
				? t('import_export.result.detected_locale')
				: t('import_export.result.detected_locales');
			$lines[] = '<b>' . e($label) . ':</b> ' . e(implode(', ', $result['detected_locales']));
		} elseif (!empty($result['detected_locale'])) {
			$lines[] = '<b>' . e(t('import_export.result.detected_locale')) . ':</b> ' . e($result['detected_locale']);
		}

		if (!empty($result['format'])) {
			$lines[] = '<b>' . e(t('import_export.result.format')) . ':</b> ' . e($result['format']);
		}

		if (!empty($result['mode'])) {
			$lines[] = '<b>' . e(t('import_export.result.mode')) . ':</b> ' . e($result['mode']);
		}

		$lines[] = '<b>' . e(t('import_export.result.processed')) . ':</b> ' . (int) $result['processed'];
		$lines[] = '<b>' . e(t('import_export.result.inserted')) . ':</b> ' . (int) $result['inserted'];
		$lines[] = '<b>' . e(t('import_export.result.updated')) . ':</b> ' . (int) $result['updated'];
		$lines[] = '<b>' . e(t('import_export.result.imported')) . ':</b> ' . (int) $result['imported'];
		$lines[] = '<b>' . e(t('import_export.result.skipped')) . ':</b> ' . (int) $result['skipped'];
		$lines[] = '<b>' . e(t('import_export.result.deleted')) . ':</b> ' . (int) $result['deleted'];

		if (!empty($result['errors'])) {
			$errorItems = '<ul><li>' . implode('</li><li>', array_map('e', $result['errors'])) . '</li></ul>';
			SystemMessages::_error(
				e(t('import_export.result.error_summary', ['dataset' => $dataset->getName()])) . '<br>'
				. implode('<br>', $lines)
				. '<br>'
				. $errorItems
			);

			return;
		}

		$message = implode('<br>', $lines);

		if ($dryRun) {
			SystemMessages::addSystemMessage(
				e(t('import_export.result.dry_run_summary', ['dataset' => $dataset->getName()])) . '<br>' . $message,
				t('system_message.default_header'),
				IconNames::INFO,
				true,
				'notice'
			);

			return;
		}

		SystemMessages::_ok(
			e(t('import_export.result.success_summary', ['dataset' => $dataset->getName()])) . '<br>' . $message,
			'',
			true
		);
	}
}
