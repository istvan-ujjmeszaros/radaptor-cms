<?php

declare(strict_types=1);

class I18nWorkbench
{
	/**
	 * @param string $locale Target locale
	 * @param array<string, mixed> $filters Supported keys: domain, search
	 * @param int $start DataTables offset
	 * @param int $length DataTables page size
	 * @return array{data: list<array<string, mixed>>, recordsTotal: int, recordsFiltered: int}
	 */
	public static function getTranslations(string $locale, array $filters, int $start, int $length): array
	{
		$locale = LocaleService::canonicalize($locale);
		$legacy_locale = LocaleService::toIntlLocale($locale);
		$pdo = Db::instance();
		[$totalWhere, $totalParams] = self::_buildWhere($filters, false);
		$totalParams[':locale'] = $locale;
		$totalParams[':legacy_locale'] = $legacy_locale;

		// Unfiltered total for the current scope — apply non-search filters such as domain,
		// but ignore the free-text search term so DataTables totals stay meaningful.
		$unfilteredStmt = $pdo->prepare(
			"SELECT COUNT(*) FROM i18n_messages m
			LEFT JOIN i18n_translations t ON t.domain = m.domain AND t.`key` = m.`key` AND t.context = m.context AND t.locale = :locale
			LEFT JOIN i18n_translations tl ON tl.domain = m.domain AND tl.`key` = m.`key` AND tl.context = m.context AND tl.locale = :legacy_locale AND tl.locale <> :locale
			{$totalWhere}"
		);
		$unfilteredStmt->execute($totalParams);
		$recordsTotal = (int) $unfilteredStmt->fetchColumn();

		[$where, $params] = self::_buildWhere($filters);
		$params[':locale'] = $locale;
		$params[':legacy_locale'] = $legacy_locale;

		// Filtered count
		$countSql = "SELECT COUNT(*) FROM i18n_messages m
			LEFT JOIN i18n_translations t ON t.domain = m.domain AND t.`key` = m.`key` AND t.context = m.context AND t.locale = :locale
			LEFT JOIN i18n_translations tl ON tl.domain = m.domain AND tl.`key` = m.`key` AND tl.context = m.context AND tl.locale = :legacy_locale AND tl.locale <> :locale
			{$where}";
		$countStmt = $pdo->prepare($countSql);
		$countStmt->execute($params);
		$recordsFiltered = (int) $countStmt->fetchColumn();

		$dataSql = "SELECT
				m.domain,
				m.`key`,
				m.context,
				m.source_text,
				COALESCE(t.text, tl.text, '') AS text,
				CASE WHEN COALESCE(t.human_reviewed, tl.human_reviewed, 0) = 1 THEN 1 ELSE 0 END AS human_reviewed,
				CASE
					WHEN t.`key` IS NULL AND tl.`key` IS NULL THEN 1
					WHEN TRIM(COALESCE(t.text, tl.text, '')) = '' THEN 1
					ELSE 0
				END AS is_missing,
				COALESCE(t.source_hash_snapshot, tl.source_hash_snapshot) AS source_hash_snapshot
			FROM i18n_messages m
			LEFT JOIN i18n_translations t ON t.domain = m.domain AND t.`key` = m.`key` AND t.context = m.context AND t.locale = :locale
			LEFT JOIN i18n_translations tl ON tl.domain = m.domain AND tl.`key` = m.`key` AND tl.context = m.context AND tl.locale = :legacy_locale AND tl.locale <> :locale
			{$where}
			ORDER BY m.domain, m.`key`, m.context
			LIMIT :length OFFSET :start";

		$dataStmt = $pdo->prepare($dataSql);

		foreach ($params as $k => $v) {
			$dataStmt->bindValue($k, $v);
		}

		$dataStmt->bindValue(':length', $length, \PDO::PARAM_INT);
		$dataStmt->bindValue(':start', $start, \PDO::PARAM_INT);
		$dataStmt->execute();

		$rows = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);
		$rows = array_map(static function (array $row) use ($locale): array {
			$row['locale'] = $locale;
			$row['human_reviewed'] = (int) ($row['human_reviewed'] ?? 0);

			return $row;
		}, $rows);

		return [
			'data'            => $rows,
			'recordsTotal'    => $recordsTotal,
			'recordsFiltered' => $recordsFiltered,
		];
	}

	/**
	 * Return exact TM suggestions for a given source message.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function getTmSuggestions(
		string $domain,
		string $key,
		string $context,
		string $targetLocale
	): array {
		$sourceText = self::getSourceText($domain, $key, $context);

		if ($sourceText === null || $sourceText === '') {
			return [];
		}

		return I18nTm::getExactSuggestions($sourceText, $targetLocale);
	}

	public static function getSourceText(string $domain, string $key, string $context): ?string
	{
		// `key` is a MySQL reserved word — use raw PDO
		$msgStmt = Db::instance()->prepare(
			"SELECT source_text FROM i18n_messages WHERE domain = ? AND `key` = ? AND context = ?"
		);
		$msgStmt->execute([$domain, $key, $context]);
		$message = $msgStmt->fetch(\PDO::FETCH_ASSOC) ?: null;

		if (!$message) {
			return null;
		}

		return (string) $message['source_text'];
	}

	/**
	 * @param array<string, mixed> $filters
	 * @return array{0: string, 1: array<string, mixed>}
	 */
	private static function _buildWhere(array $filters, bool $includeSearch = true): array
	{
		$conditions = [];
		$params = [];

		if (!empty($filters['domain'])) {
			$conditions[] = 'm.domain = :domain';
			$params[':domain'] = $filters['domain'];
		}

		if ($includeSearch && !empty($filters['search'])) {
			$conditions[] = "(
				m.`key` LIKE :search_key
				OR m.source_text LIKE :search_source
				OR COALESCE(t.text, tl.text, '') LIKE :search_text
				OR CONCAT(m.domain, '.', m.`key`) LIKE :search_qualified
				OR CONCAT(m.domain, '.', m.`key`, '.', m.context) LIKE :search_qualified_with_context
			)";
			$search = '%' . $filters['search'] . '%';
			$params[':search_key'] = $search;
			$params[':search_source'] = $search;
			$params[':search_text'] = $search;
			$params[':search_qualified'] = $search;
			$params[':search_qualified_with_context'] = $search;
		}

		$where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);

		return [$where, $params];
	}
}
