<?php

declare(strict_types=1);

final class I18nTranslationsWideCsv
{
	private const string _BOM = "\xEF\xBB\xBF";

	public static function detectFormat(string $csvContent): string
	{
		$csvContent = ltrim($csvContent, self::_BOM);

		$handle = fopen('php://temp', 'r+');
		fwrite($handle, $csvContent);
		rewind($handle);

		$headers = fgetcsv($handle, 0, ',', '"', '');
		fclose($handle);

		if ($headers === false) {
			return 'normalized';
		}

		$headers = CsvHelper::normalizeHeaderRow(array_map('strval', $headers));

		foreach ($headers as $header) {
			if (str_starts_with($header, 'text:')) {
				return 'wide';
			}
		}

		return 'normalized';
	}

	/**
	 * @param array<string, mixed> $filters
	 */
	public static function export(array $filters = []): string
	{
		$map = new I18nTranslationsCsvMap();
		$rows = iterator_to_array($map->exportRows($filters), false);

		$locales = [];
		$grouped = [];

		foreach ($rows as $row) {
			$domain = (string) ($row['domain'] ?? '');
			$key = (string) ($row['key'] ?? '');
			$context = (string) ($row['context'] ?? '');
			$sourceText = (string) ($row['source_text'] ?? '');
			$locale = (string) ($row['locale'] ?? '');
			$text = (string) ($row['text'] ?? '');
			$allowSourceMatch = ((string) ($row['allow_source_match'] ?? '')) === '1' ? '1' : '0';
			$locale = LocaleService::tryCanonicalize($locale) ?? $locale;

			if ($locale === '') {
				continue;
			}

			$locales[$locale] = true;
			$groupKey = self::_buildGroupKey($domain, $key, $context);

			if (!isset($grouped[$groupKey])) {
				$grouped[$groupKey] = [
					'domain' => $domain,
					'key' => $key,
					'context' => $context,
					'source_text' => $sourceText,
					'texts' => [],
					'allow_source_matches' => [],
				];
			}

			$grouped[$groupKey]['source_text'] = $sourceText;
			$grouped[$groupKey]['texts'][$locale] = $text;
			$grouped[$groupKey]['allow_source_matches'][$locale] = $allowSourceMatch;
		}

		$localeColumns = array_keys($locales);
		sort($localeColumns);

		$headers = ['domain', 'key', 'context', 'source_text'];

		foreach ($localeColumns as $locale) {
			$headers[] = 'text:' . $locale;
		}

		foreach ($localeColumns as $locale) {
			$headers[] = 'allow_source_match:' . $locale;
		}

		$buf = fopen('php://temp', 'r+');
		fwrite($buf, self::_BOM);
		fputcsv($buf, $headers, ',', '"', '');

		foreach ($grouped as $row) {
			$line = [
				$row['domain'],
				$row['key'],
				$row['context'],
				$row['source_text'],
			];

			foreach ($localeColumns as $locale) {
				$line[] = (string) ($row['texts'][$locale] ?? '');
			}

			foreach ($localeColumns as $locale) {
				$line[] = (string) ($row['allow_source_matches'][$locale] ?? '0');
			}

			fputcsv($buf, $line, ',', '"', '');
		}

		rewind($buf);
		$csv = stream_get_contents($buf);
		fclose($buf);

		return $csv !== false ? $csv : '';
	}

	/**
	 * Convert a wide i18n CSV into the canonical normalized CSV shape:
	 * domain,key,context,locale,source_text,expected_text,human_reviewed,allow_source_match,text
	 *
	 * Empty translation cells are skipped.
	 *
	 * @throws InvalidArgumentException
	 */
	public static function toNormalizedCsv(string $csvContent): string
	{
		$csvContent = ltrim($csvContent, self::_BOM);

		$handle = fopen('php://temp', 'r+');
		fwrite($handle, $csvContent);
		rewind($handle);

		$headers = fgetcsv($handle, 0, ',', '"', '');

		if ($headers === false) {
			fclose($handle);

			throw new InvalidArgumentException('CSV is empty or unreadable');
		}

		$headers = CsvHelper::normalizeHeaderRow(array_map('strval', $headers));

		$required = ['domain', 'key', 'context', 'source_text'];
		$errors = [];

		foreach ($required as $column) {
			if (!in_array($column, $headers, true)) {
				$errors[] = "Required column missing: {$column}";
			}
		}

		$localeColumns = [];
		$allowSourceMatchColumns = [];

		foreach ($headers as $header) {
			if (str_starts_with($header, 'text:')) {
				$locale = LocaleService::tryCanonicalize(substr($header, 5)) ?? trim(substr($header, 5));

				if ($locale === '') {
					$errors[] = 'Wide CSV contains an empty locale column name';

					continue;
				}

				if (!LocaleRegistry::isKnownLocale($locale)) {
					$errors[] = "Wide CSV contains unsupported locale column: text:{$locale}";

					continue;
				}
				$localeColumns[$header] = $locale;

				continue;
			}

			if (str_starts_with($header, 'allow_source_match:')) {
				$locale = LocaleService::tryCanonicalize(substr($header, 19)) ?? trim(substr($header, 19));

				if ($locale === '') {
					$errors[] = 'Wide CSV contains an empty allow_source_match locale column name';

					continue;
				}

				if (!LocaleRegistry::isKnownLocale($locale)) {
					$errors[] = "Wide CSV contains unsupported locale column: allow_source_match:{$locale}";

					continue;
				}

				$allowSourceMatchColumns[$locale] = $header;

				continue;
			}

			if (!in_array($header, $required, true)) {
				$errors[] = "Unknown column: {$header}";
			}
		}

		if (empty($localeColumns)) {
			$errors[] = t('import_export.error.wide_no_locale_columns');
		}

		if (!empty($errors)) {
			fclose($handle);

			throw new InvalidArgumentException(implode("\n", array_unique($errors)));
		}

		$buf = fopen('php://temp', 'r+');
		fwrite($buf, self::_BOM);
		fputcsv($buf, I18nCsvSchema::NORMALIZED_HEADER, ',', '"', '');

		$lineNumber = 1;

		while (($rawRow = fgetcsv($handle, 0, ',', '"', '')) !== false) {
			$lineNumber++;

			if (CsvHelper::isIgnorableRawRow($rawRow)) {
				continue;
			}

			$row = [];

			foreach ($headers as $i => $col) {
				$row[$col] = trim((string) ($rawRow[$i] ?? ''));
			}

			foreach ($localeColumns as $column => $locale) {
				$text = (string) ($row[$column] ?? '');

				if ($text === '') {
					continue;
				}

				fputcsv($buf, [
					(string) ($row['domain'] ?? ''),
					(string) ($row['key'] ?? ''),
					(string) ($row['context'] ?? ''),
					$locale,
					(string) ($row['source_text'] ?? ''),
					'',
					'',
					(string) ($row[$allowSourceMatchColumns[$locale] ?? ''] ?? ''),
					$text,
				], ',', '"', '');
			}
		}

		fclose($handle);
		rewind($buf);
		$normalized = stream_get_contents($buf);
		fclose($buf);

		return $normalized !== false ? $normalized : '';
	}

	private static function _buildGroupKey(string $domain, string $key, string $context): string
	{
		return $domain . '|' . $key . '|' . $context;
	}
}
