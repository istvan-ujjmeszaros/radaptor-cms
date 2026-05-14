<?php

declare(strict_types=1);

/**
 * CSV map for i18n translations.
 *
 * Exports from i18n_messages JOIN i18n_translations.
 * Imports via I18nTranslationService so every write path shares the same
 * translation + TM synchronization rules.
 *
 * Supported import modes: InsertNew, Upsert, Sync.
 *   - InsertNew  Add new keys only; skip any key+locale that already exists.
 *   - Upsert     (default) Insert new or update existing; never deletes.
 *                Use this when importing a file from an external translator.
 *   - Sync       Upsert + delete translations whose domain/key/context/locale
 *                is absent from the CSV. Use this to clean up obsolete keys
 *                after a big-bang literal migration removes them from code.
 *
 * The CSV keeps locale as an explicit column.
 *   - InsertNew / Upsert allow mixed-locale files.
 *   - Sync requires a single-locale file so deletion scope remains explicit.
 *
 * CSV columns:
 *   domain        required  — e.g. 'user', 'ticket'
 *   key           required  — e.g. 'field.username.label'
 *   context       optional  — default ''
 *   locale        required  — e.g. 'hu-HU'
 *   source_text   optional  — English canonical text; used to create a
 *                             missing i18n_messages row during import
 *   expected_text optional  — compare-and-swap guard for human-reviewed rows;
 *                             when present and non-empty, reviewed rows only
 *                             update if the current DB text still matches.
 *                             Unreviewed rows may still be upserted so shipped
 *                             seeds can replace placeholders.
 *   human_reviewed optional — when present:
 *                             1 promotes the row to reviewed,
 *                             0 keeps reviewed rows reviewed and only affects
 *                               currently unreviewed rows,
 *                             empty preserves the current DB flag
 *   allow_source_match optional — when present:
 *                             1 marks an intentional source-text match,
 *                             0 clears the flag,
 *                             empty preserves the current DB flag when still eligible
 *   text          required  — translated string
 */
class I18nTranslationsCsvMap implements iCsvMap
{
	/** @var array<string, true> Tracks the locale scope of the imported CSV */
	private array $_importedLocales = [];

	/** @var array<string, true> Tracks the domain scope of the imported CSV */
	private array $_importedDomains = [];

	// -------------------------------------------------------------------------
	// iCsvMap implementation
	// -------------------------------------------------------------------------

	public function getColumnDefinitions(): array
	{
		return [
			'domain'      => ['required' => true],
			'key'         => ['required' => true],
			'context'     => ['required' => false, 'default' => ''],
			'locale'      => ['required' => true],
			'source_text' => ['required' => false, 'default' => ''],
			'expected_text' => ['required' => false, 'default' => ''],
			'human_reviewed' => ['required' => false, 'default' => ''],
			'allow_source_match' => ['required' => false, 'default' => ''],
			'text'        => ['required' => true],
		];
	}

	public function getNaturalKeyColumns(): array
	{
		return ['domain', 'key', 'context', 'locale'];
	}

	public function exportRows(array $filters = []): iterable
	{
		$pdo = Db::instance();

		$where_parts = [];
		$params = [];

		if (!empty($filters['locale']) && is_string($filters['locale'])) {
			$requested_locale = trim($filters['locale']);
			$canonical_locale = LocaleService::tryCanonicalize($requested_locale) ?? $requested_locale;
			$locale_values = array_values(array_unique(array_filter([
				$canonical_locale,
				$requested_locale,
				LocaleService::toIntlLocale($canonical_locale),
			], static fn (string $locale): bool => $locale !== '')));
			$where_parts[] = 't.locale IN (' . implode(', ', array_fill(0, count($locale_values), '?')) . ')';
			$params = [...$params, ...$locale_values];
		}

		if (!empty($filters['domains']) && is_array($filters['domains'])) {
			$domains = array_values(array_filter(
				array_map(static fn (mixed $domain): string => trim((string) $domain), $filters['domains']),
				static fn (string $domain): bool => $domain !== ''
			));

			if (!empty($domains)) {
				$placeholders = implode(', ', array_fill(0, count($domains), '?'));
				$where_parts[] = "m.domain IN ({$placeholders})";
				$params = [...$params, ...$domains];
			}
		}

		if (!empty($filters['key_prefixes']) && is_array($filters['key_prefixes'])) {
			$key_prefixes = array_values(array_filter(
				array_map(static fn (mixed $prefix): string => trim((string) $prefix), $filters['key_prefixes']),
				static fn (string $prefix): bool => $prefix !== ''
			));

			if (!empty($key_prefixes)) {
				$key_parts = [];

				foreach ($key_prefixes as $prefix) {
					$key_parts[] = 'm.`key` LIKE ?';
					$params[] = $prefix . '%';
				}

				$where_parts[] = '(' . implode(' OR ', $key_parts) . ')';
			}
		}

		$where = empty($where_parts)
			? ''
			: 'WHERE ' . implode(' AND ', $where_parts);

		$stmt = $pdo->prepare(
			"SELECT m.domain, m.`key`, m.context, t.locale,
				m.source_text, t.text AS expected_text,
				CASE WHEN t.human_reviewed = 1 THEN '1' ELSE '0' END AS human_reviewed,
				CASE WHEN t.allow_source_match = 1 THEN '1' ELSE '0' END AS allow_source_match,
				t.text
			FROM i18n_messages m
			JOIN i18n_translations t
				ON t.domain = m.domain AND t.`key` = m.`key` AND t.context = m.context
			{$where}
			ORDER BY m.domain, m.`key`, m.context, t.locale"
		);

		$stmt->execute($params);

		while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			yield $row;
		}
	}

	/**
	 * Validate import locale scope and return the distinct locales present in the CSV.
	 *
	 * InsertNew / Upsert allow mixed-locale files.
	 * Sync requires a single-locale file to keep deletion scope explicit.
	 *
	 * @return array{locales: list<string>, errors: list<string>}
	 */
	public function validateImportLocaleScope(
		string $csvContent,
		CsvImportMode $mode = CsvImportMode::Upsert,
		string $expectedLocale = ''
	): array {
		$expectedLocale = trim($expectedLocale);

		if ($expectedLocale !== '') {
			$expectedLocale = LocaleService::tryCanonicalize($expectedLocale) ?? $expectedLocale;
		}

		if ($expectedLocale !== '' && !LocaleRegistry::isKnownLocale($expectedLocale)) {
			return [
				'locales' => [],
				'errors' => [
					"Expected locale '{$expectedLocale}' is not a supported standard locale",
				],
			];
		}

		$csvContent = ltrim($csvContent, "\xEF\xBB\xBF");

		$handle = fopen('php://temp', 'r+');
		fwrite($handle, $csvContent);
		rewind($handle);

		$headers = fgetcsv($handle, 0, ',', '"', '');

		if ($headers === false) {
			fclose($handle);

			return ['locales' => [], 'errors' => ['CSV is empty or unreadable']];
		}

		$headers = array_map('trim', $headers);
		$errors = CsvHelper::validateHeaders($headers, $this);
		$localeIndex = array_search('locale', $headers, true);

		if ($localeIndex === false) {
			fclose($handle);

			return ['locales' => [], 'errors' => $errors];
		}

		$locales = [];
		$lineNumber = 1;

		while (($rawRow = fgetcsv($handle, 0, ',', '"', '')) !== false) {
			$lineNumber++;

			if (CsvHelper::isIgnorableRawRow($rawRow)) {
				continue;
			}

			$locale = trim((string) ($rawRow[$localeIndex] ?? ''));

			if ($locale === '') {
				$errors[] = "Line {$lineNumber}: locale is required";

				continue;
			}

			$canonicalLocale = LocaleService::tryCanonicalize($locale);

			if ($canonicalLocale === null || !LocaleRegistry::isKnownLocale($canonicalLocale)) {
				$errors[] = "Line {$lineNumber}: locale '{$locale}' is not a supported standard locale";

				continue;
			}

			$locales[$canonicalLocale] = true;
		}

		fclose($handle);

		if (!empty($errors)) {
			return ['locales' => [], 'errors' => $errors];
		}

		if (empty($locales)) {
			return ['locales' => [], 'errors' => ['CSV contains no data rows']];
		}

		ksort($locales);
		$distinctLocales = array_keys($locales);

		if ($expectedLocale !== '' && ($distinctLocales !== [$expectedLocale])) {
			return [
				'locales' => [],
				'errors' => [
					"Expected locale '{$expectedLocale}', but the CSV contains locale(s): " . implode(', ', $distinctLocales),
				],
			];
		}

		if ($mode === CsvImportMode::Sync && count($distinctLocales) !== 1) {
			return [
				'locales' => [],
				'errors' => [
					'Sync mode requires a single locale per file. Found: ' . implode(', ', $distinctLocales),
				],
			];
		}

		return ['locales' => $distinctLocales, 'errors' => []];
	}

	public function importRow(array $row, CsvImportMode $mode, bool $dryRun = false): array
	{
		$domain = trim((string) ($row['domain'] ?? ''));
		$locale = LocaleService::canonicalize((string) ($row['locale'] ?? ''));
		$row['locale'] = $locale;

		if ($domain !== '') {
			$this->_importedDomains[$domain] = true;
		}

		$this->_importedLocales[$locale] = true;

		return I18nTranslationService::applyImportRow($row, $mode, $dryRun);
	}

	public function deleteAbsentRows(array $importedNaturalKeys, bool $dryRun = false): array
	{
		$pdo = Db::instance();
		$importedLocales = array_keys($this->_importedLocales);
		$importedDomains = array_keys($this->_importedDomains);

		if (empty($importedLocales) || empty($importedDomains)) {
			return [];
		}

		$localePlaceholders = implode(', ', array_fill(0, count($importedLocales), '?'));
		$domainPlaceholders = implode(', ', array_fill(0, count($importedDomains), '?'));

		// Fetch existing rows only within the imported locale + domain scope.
		$stmt = $pdo->prepare(
			"SELECT domain, `key`, context, locale
			FROM i18n_translations
			WHERE locale IN ({$localePlaceholders})
				AND domain IN ({$domainPlaceholders})"
		);
		$stmt->execute([...$importedLocales, ...$importedDomains]);
		$existing = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		$importedSet = array_flip($importedNaturalKeys);
		$deletedRows = [];

		foreach ($existing as $row) {
			$natKey = "domain={$row['domain']}|key={$row['key']}|context={$row['context']}|locale={$row['locale']}";

			if (!isset($importedSet[$natKey])) {
				$deletedRows[] = I18nTranslationService::deleteTranslation(
					(string) $row['domain'],
					(string) $row['key'],
					(string) $row['context'],
					(string) $row['locale'],
					$dryRun
				);
			}
		}

		return $deletedRows;
	}

	public function getSupportedImportModes(): array
	{
		return [
			CsvImportMode::InsertNew,
			CsvImportMode::Upsert,
			CsvImportMode::Sync,
		];
	}

	public function getDefaultImportMode(): CsvImportMode
	{
		return CsvImportMode::Upsert;
	}
}
