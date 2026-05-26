<?php

declare(strict_types=1);

final class I18nReferenceAuditService
{
	/**
	 * @param array{
	 *     all_packages?: bool,
	 *     locales?: list<string>,
	 *     seed_targets?: list<array<string, mixed>>,
	 *     descriptor_rows?: list<array<string, mixed>>
	 * } $options
	 * @return array{
	 *     status: string,
	 *     locales: list<string>,
	 *     descriptors_scanned: int,
	 *     references_scanned: int,
	 *     missing_references: int,
	 *     issues: list<array<string, mixed>>
	 * }
	 */
	public static function audit(array $options = []): array
	{
		$locales = self::normalizeLocales($options['locales'] ?? I18nRuntime::getAvailableLocaleCodes());

		if ($locales === []) {
			$locales = [self::getDefaultLocale()];
		}

		$seed_targets = $options['seed_targets'] ?? I18nSeedTargetDiscovery::discoverTargets([
			'all_packages' => (bool)($options['all_packages'] ?? false),
		]);
		$descriptor_rows = $options['descriptor_rows'] ?? self::loadCaptureDescriptorRows();
		$seed_index = self::buildSeedIndex($seed_targets, $locales);
		$issues = [];
		$references_scanned = 0;

		foreach ($descriptor_rows as $row) {
			$descriptor = self::decodeDescriptorRow($row);

			if (!is_array($descriptor)) {
				$issues[] = [
					'code' => 'i18n_reference_descriptor_invalid_json',
					'definition_slug' => (string)($row['definition_slug'] ?? ''),
					'version_id' => (int)($row['version_id'] ?? 0),
				];

				continue;
			}

			foreach (self::extractI18nKeys($descriptor) as $key) {
				$references_scanned++;

				foreach ($locales as $locale) {
					if (isset($seed_index[$locale][$key])) {
						continue;
					}

					$issues[] = [
						'code' => 'i18n_reference_missing_seed',
						'key' => $key,
						'locale' => $locale,
						'definition_slug' => (string)($row['definition_slug'] ?? ''),
						'source' => (string)($row['source'] ?? ''),
						'version_id' => (int)($row['version_id'] ?? 0),
						'version_number' => (int)($row['version_number'] ?? 0),
					];
				}
			}
		}

		return [
			'status' => $issues === [] ? 'ok' : 'error',
			'locales' => $locales,
			'descriptors_scanned' => count($descriptor_rows),
			'references_scanned' => $references_scanned,
			'missing_references' => count($issues),
			'issues' => $issues,
		];
	}

	/**
	 * @return list<array<string, int|string|bool>>
	 */
	private static function loadCaptureDescriptorRows(): array
	{
		if (
			!DbSchemaHelper::tableExists('form_definitions')
			|| !DbSchemaHelper::tableExists('form_definition_versions')
		) {
			return [];
		}

		return DbHelper::selectManyFromQuery(
			"SELECT
				d.definition_slug,
				d.source,
				v.version_id,
				v.version_number,
				v.status,
				v.descriptor_json
			FROM form_definitions d
			INNER JOIN form_definition_versions v
				ON v.definition_id = d.definition_id
			WHERE d.kind = 'capture'
				AND d.source <> 'db'
				AND (v.status = 'published' OR v.version_id = d.published_version_id)
			ORDER BY d.definition_slug, v.version_number"
		);
	}

	/**
	 * @param list<array<string, mixed>> $targets
	 * @param list<string> $locales
	 * @return array<string, array<string, true>>
	 */
	private static function buildSeedIndex(array $targets, array $locales): array
	{
		$index = [];

		foreach ($locales as $locale) {
			$index[$locale] = [];
		}

		foreach ($targets as $target) {
			$input_dir = rtrim((string)($target['input_dir'] ?? ''), '/');

			if ($input_dir === '' || !is_dir($input_dir)) {
				continue;
			}

			foreach ($locales as $locale) {
				$file = $input_dir . '/' . $locale . '.csv';

				if (!is_file($file)) {
					continue;
				}

				foreach (self::readSeedKeys($file) as $key) {
					$index[$locale][$key] = true;
				}
			}
		}

		return $index;
	}

	/**
	 * @return list<string>
	 */
	private static function readSeedKeys(string $file): array
	{
		$handle = fopen($file, 'r');

		if ($handle === false) {
			return [];
		}

		$header = fgetcsv($handle, 0, ',', '"', '');

		if ($header === false) {
			fclose($handle);

			return [];
		}

		$header = array_map(static function (string $column): string {
			return trim($column, "\xEF\xBB\xBF \t\n\r\0\x0B");
		}, array_map('strval', $header));
		$indexes = array_flip($header);
		$keys = [];

		while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
			if (self::isIgnorableCsvRow($row)) {
				continue;
			}

			$domain = trim((string)($row[$indexes['domain'] ?? -1] ?? ''));
			$key = trim((string)($row[$indexes['key'] ?? -1] ?? ''));

			if ($domain === '' || $key === '') {
				continue;
			}

			$keys[] = $domain . '.' . $key;
		}

		fclose($handle);
		sort($keys);

		return array_values(array_unique($keys));
	}

	/**
	 * @param array<int, mixed> $row
	 */
	private static function isIgnorableCsvRow(array $row): bool
	{
		foreach ($row as $value) {
			if (trim((string)$value) !== '') {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>|null
	 */
	private static function decodeDescriptorRow(array $row): ?array
	{
		$descriptor = $row['descriptor'] ?? null;

		if (is_array($descriptor)) {
			return $descriptor;
		}

		$descriptor_json = (string)($row['descriptor_json'] ?? '');

		if ($descriptor_json === '') {
			return null;
		}

		try {
			$decoded = json_decode($descriptor_json, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return null;
		}

		return is_array($decoded) ? $decoded : null;
	}

	/**
	 * @param array<string, mixed> $descriptor
	 * @return list<string>
	 */
	private static function extractI18nKeys(array $descriptor): array
	{
		$keys = [];
		self::collectI18nKeys($descriptor, $keys);
		sort($keys);

		return array_values(array_unique($keys));
	}

	/**
	 * @param array<int|string, mixed> $value
	 * @param list<string> $keys
	 */
	private static function collectI18nKeys(array $value, array &$keys): void
	{
		$key = $value['key'] ?? null;

		if (is_string($key) && self::isI18nKey($key)) {
			$keys[] = $key;
		}

		foreach ($value as $child) {
			if (is_array($child)) {
				self::collectI18nKeys($child, $keys);
			}
		}
	}

	private static function isI18nKey(string $key): bool
	{
		return preg_match('/^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)+$/', $key) === 1;
	}

	/**
	 * @param mixed $locales
	 * @return list<string>
	 */
	private static function normalizeLocales(mixed $locales): array
	{
		if (!is_array($locales)) {
			return [];
		}

		$normalized = [];

		foreach ($locales as $locale) {
			$locale = self::tryCanonicalizeLocale((string)$locale);

			if ($locale !== null && $locale !== '') {
				$normalized[] = $locale;
			}
		}

		$normalized = array_values(array_unique($normalized));
		sort($normalized);

		return $normalized;
	}

	private static function getDefaultLocale(): string
	{
		if (class_exists('LocaleService') && method_exists('LocaleService', 'getDefaultLocale')) {
			return LocaleService::getDefaultLocale();
		}

		return 'en-US';
	}

	private static function tryCanonicalizeLocale(string $locale): ?string
	{
		if (class_exists('LocaleService') && method_exists('LocaleService', 'tryCanonicalize')) {
			return LocaleService::tryCanonicalize($locale);
		}

		$locale = trim($locale);

		if (preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale) !== 1) {
			return null;
		}

		return $locale;
	}
}
