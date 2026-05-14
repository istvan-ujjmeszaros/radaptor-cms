<?php

declare(strict_types=1);

class ImportExportDatasetI18nTranslations extends AbstractImportExportDataset
{
	public const string ID = 'i18n_translations';

	public function getName(): string
	{
		return t('import_export.dataset.i18n_translations.name');
	}

	public function getDescription(): string
	{
		return t('import_export.dataset.i18n_translations.description');
	}

	public function getListVisibility(): bool
	{
		return Roles::hasRole(RoleList::ROLE_I18N_TRANSLATOR);
	}

	public function getExportFieldDefinitions(): array
	{
		$options = ['' => t('import_export.field.locale.all')] + $this->_getLocaleOptions();

		return [
			'format' => [
				'type' => 'select',
				'label' => t('import_export.field.format.label'),
				'required' => true,
				'default' => 'normalized',
				'help' => t('import_export.field.format.help'),
				'options' => [
					'normalized' => t('import_export.format.normalized'),
					'wide' => t('import_export.format.wide'),
				],
			],
			'locale' => [
				'type' => 'select',
				'label' => t('import_export.field.locale.label'),
				'required' => false,
				'default' => '',
				'help' => t('import_export.field.locale.help'),
				'options' => $options,
			],
		];
	}

	public function getImportFieldDefinitions(): array
	{
		return [
			'csv_file' => [
				'type' => 'file',
				'label' => t('import_export.field.file.label'),
				'required' => true,
				'accept' => '.csv,text/csv',
				'help' => t('import_export.field.file.help'),
			],
			'format' => [
				'type' => 'select',
				'label' => t('import_export.field.format.label'),
				'required' => true,
				'default' => 'auto',
				'help' => t('import_export.field.format.help'),
				'options' => [
					'auto' => t('import_export.format.auto'),
					'normalized' => t('import_export.format.normalized'),
					'wide' => t('import_export.format.wide'),
				],
			],
			'mode' => [
				'type' => 'select',
				'label' => t('import_export.field.mode.label'),
				'required' => true,
				'default' => CsvImportMode::Upsert->value,
				'help' => t('import_export.field.mode.help'),
				'options' => [
					CsvImportMode::InsertNew->value => t('import_export.mode.insert_new'),
					CsvImportMode::Upsert->value => t('import_export.mode.upsert'),
					CsvImportMode::Sync->value => t('import_export.mode.sync'),
				],
			],
		];
	}

	public function export(array $options): string
	{
		$format = $this->_normalizeFormat($options['format'] ?? 'normalized');
		$locale = LocaleService::tryCanonicalize((string) ($options['locale'] ?? '')) ?? trim((string) ($options['locale'] ?? ''));
		$filters = [];

		if ($locale !== '') {
			$filters['locale'] = $locale;
		}

		if (array_key_exists('domains', $options)) {
			$domains = $options['domains'];

			if (is_array($domains) && $domains !== []) {
				$filters['domains'] = $domains;
			}
		}

		if (array_key_exists('key_prefixes', $options)) {
			$key_prefixes = $options['key_prefixes'];

			if (is_array($key_prefixes) && $key_prefixes !== []) {
				$filters['key_prefixes'] = $key_prefixes;
			}
		}

		if ($format === 'wide') {
			return I18nTranslationsWideCsv::export($filters);
		}

		return CsvHelper::export(new I18nTranslationsCsvMap(), $filters);
	}

	public function buildExportFilename(array $options): string
	{
		$format = $this->_normalizeFormat($options['format'] ?? 'normalized');
		$locale = LocaleService::tryCanonicalize((string) ($options['locale'] ?? '')) ?? trim((string) ($options['locale'] ?? ''));
		$localeScope = $locale !== '' ? $locale : 'all_locales';
		$timestamp = date('Ymd_His');

		return $this->getKey() . '.' . $format . '.' . $localeScope . '.' . $timestamp . '.csv';
	}

	public function import(string $csvContent, array $options): array
	{
		$format = $this->_normalizeImportFormat($options['format'] ?? 'auto', $csvContent);
		$modeValue = trim($options['mode'] ?? CsvImportMode::Upsert->value);

		if ($modeValue === '') {
			$modeValue = CsvImportMode::Upsert->value;
		}
		$mode = CsvImportMode::tryFrom($modeValue);

		if ($mode === null) {
			throw new InvalidArgumentException(t('import_export.error.invalid_mode'));
		}

		$expectedLocale = LocaleService::tryCanonicalize((string) ($options['expect_locale'] ?? '')) ?? trim((string) ($options['expect_locale'] ?? ''));
		$dryRun = ($options['dry_run'] ?? '0') === '1';
		$warnings = $this->_detectImportWarnings($csvContent, $format);
		$normalizedCsv = $format === 'wide'
			? I18nTranslationsWideCsv::toNormalizedCsv($csvContent)
			: $csvContent;

		$map = new I18nTranslationsCsvMap();
		$scope = $map->validateImportLocaleScope($normalizedCsv, $mode, $expectedLocale);

		if (!empty($scope['errors'])) {
			throw new InvalidArgumentException(implode("\n", $scope['errors']));
		}

		$result = CsvHelper::import($normalizedCsv, $map, $mode, $dryRun);
		$result['warnings'] = $warnings;
		$result['detected_locales'] = $scope['locales'];

		if (count($scope['locales']) === 1) {
			$result['detected_locale'] = $scope['locales'][0];
		}
		$result['mode'] = $mode->value;
		$result['format'] = $format;

		if (
			!$dryRun
			&& empty($result['errors'])
			&& getenv('ENVIRONMENT') !== 'test'
		) {
			foreach ($scope['locales'] as $locale) {
				I18nCatalogBuilder::build($locale);
			}
		}

		return $result;
	}

	private function _getLocaleOptions(): array
	{
		return I18nRuntime::getAvailableLocaleOptionMap();
	}

	private function _normalizeFormat(string $format): string
	{
		$format = trim($format);

		if (!in_array($format, ['normalized', 'wide'], true)) {
			throw new InvalidArgumentException(t('import_export.error.invalid_format'));
		}

		return $format;
	}

	private function _normalizeImportFormat(string $format, string $csvContent): string
	{
		$format = trim($format);

		if ($format === '' || $format === 'auto') {
			return I18nTranslationsWideCsv::detectFormat($csvContent);
		}

		if ($format === 'normalized' && I18nTranslationsWideCsv::detectFormat($csvContent) === 'wide') {
			return 'wide';
		}

		return $this->_normalizeFormat($format);
	}

	/**
	 * @return list<string>
	 */
	private function _detectImportWarnings(string $csvContent, string $format): array
	{
		$csvContent = ltrim($csvContent, "\xEF\xBB\xBF");
		$handle = fopen('php://temp', 'r+');

		if ($handle === false) {
			return [];
		}

		fwrite($handle, $csvContent);
		rewind($handle);
		$headers = fgetcsv($handle, 0, ',', '"', '');
		fclose($handle);

		if ($headers === false) {
			return [];
		}

		$headers = CsvHelper::normalizeHeaderRow(array_map('strval', $headers));

		if ($format === 'normalized' && !in_array('allow_source_match', $headers, true)) {
			return [t('import_export.warning.allow_source_match_missing')];
		}

		if ($format !== 'wide') {
			return [];
		}

		$textLocales = [];
		$allowSourceMatchLocales = [];

		foreach ($headers as $header) {
			if (str_starts_with($header, 'text:')) {
				$locale = LocaleService::tryCanonicalize(substr($header, 5)) ?? trim(substr($header, 5));

				if ($locale !== '') {
					$textLocales[$locale] = true;
				}

				continue;
			}

			if (str_starts_with($header, 'allow_source_match:')) {
				$locale = LocaleService::tryCanonicalize(substr($header, 19)) ?? trim(substr($header, 19));

				if ($locale !== '') {
					$allowSourceMatchLocales[$locale] = true;
				}
			}
		}

		foreach (array_keys($textLocales) as $locale) {
			if (!isset($allowSourceMatchLocales[$locale])) {
				return [t('import_export.warning.allow_source_match_missing')];
			}
		}

		if ($textLocales !== [] && $allowSourceMatchLocales === []) {
			return [t('import_export.warning.allow_source_match_missing')];
		}

		return [];
	}
}
