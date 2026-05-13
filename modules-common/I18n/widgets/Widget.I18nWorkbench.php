<?php

class WidgetI18nWorkbench extends AbstractWidget
{
	public const string ID = 'i18n_workbench';

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
		return Roles::hasRole(RoleList::ROLE_I18N_TRANSLATOR);
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path'          => '/admin/i18n/',
			'resource_name' => 'index.html',
			'layout'        => 'admin_default',
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		$localeOptions = I18nRuntime::getAvailableLocales();
		$domainOptions = $this->_getDomainOptions();
		$selectedLocale = LocaleService::tryCanonicalize((string) Request::_GET('locale', Kernel::getLocale())) ?? Kernel::getLocale();
		$selectedDomain = Request::_GET('domain', '');
		$selectedSearch = Request::_GET('search', '');

		if (!empty($localeOptions) && !in_array($selectedLocale, array_column($localeOptions, 'value'), true)) {
			$selectedLocale = $localeOptions[0]['value'];
		}

		if (
			$selectedDomain !== ''
			&& !in_array($selectedDomain, array_column($domainOptions, 'value'), true)
		) {
			$selectedDomain = '';
		}

		return $this->createComponentTree('i18nWorkbench', [
			'locale_options' => $localeOptions,
			'domain_options' => $domainOptions,
			'selected_locale' => $selectedLocale,
			'selected_domain' => $selectedDomain,
			'selected_search' => $selectedSearch,
			'coverage_summary' => $this->_getCoverageSummary($localeOptions),
		]);
	}

	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return Roles::hasRole(RoleList::ROLE_I18N_TRANSLATOR);
	}

	/**
	 * @return list<array{value: string, label: string}>
	 */
	private function _getDomainOptions(): array
	{
		$rows = Db::instance()->query(
			"SELECT DISTINCT domain
			FROM i18n_messages
			WHERE domain <> ''
			ORDER BY domain"
		)->fetchAll(PDO::FETCH_COLUMN);

		return array_map(
			static fn (string $domain): array => [
				'value' => $domain,
				'label' => $domain,
			],
			array_map('strval', $rows ?: [])
		);
	}

	/**
	 * @param list<array{value: string, label: string}> $localeOptions
	 * @return array<string, mixed>
	 */
	private function _getCoverageSummary(array $localeOptions): array
	{
		$locales = array_values(array_map(
			static fn (array $option): string => (string) $option['value'],
			$localeOptions
		));

		if (!class_exists('I18nCoverageService')) {
			return $this->_summarizeCoverage($locales);
		}

		return I18nCoverageService::summarize([
			'locales' => $locales,
		]);
	}

	/**
	 * @param list<string> $locales
	 * @return array<string, mixed>
	 */
	private function _summarizeCoverage(array $locales): array
	{
		$totalMessages = $this->_countMessages();
		$localeSummaries = [];

		foreach ($locales as $locale) {
			$localeSummaries[] = $this->_summarizeLocaleCoverage($locale, $totalMessages);
		}

		return [
			'status' => $this->_coverageStatus($localeSummaries),
			'total_messages' => $totalMessages,
			'locales' => $localeSummaries,
			'domains' => [],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function _summarizeLocaleCoverage(string $locale, int $totalMessages): array
	{
		$stmt = Db::instance()->prepare(
			"SELECT
				SUM(CASE WHEN t.`key` IS NOT NULL AND TRIM(COALESCE(t.text, '')) <> '' THEN 1 ELSE 0 END) AS translated,
				SUM(CASE WHEN t.`key` IS NULL OR TRIM(COALESCE(t.text, '')) = '' THEN 1 ELSE 0 END) AS missing,
				SUM(CASE WHEN t.human_reviewed = 1 AND TRIM(COALESCE(t.text, '')) <> '' THEN 1 ELSE 0 END) AS reviewed,
				SUM(CASE WHEN t.`key` IS NOT NULL AND t.source_hash_snapshot <> m.source_hash THEN 1 ELSE 0 END) AS stale
			FROM i18n_messages m
			LEFT JOIN i18n_translations t
				ON t.domain = m.domain
				AND t.`key` = m.`key`
				AND t.context = m.context
				AND t.locale = :locale"
		);
		$stmt->execute([':locale' => $locale]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
		$translated = (int) ($row['translated'] ?? 0);
		$missing = (int) ($row['missing'] ?? 0);
		$reviewed = (int) ($row['reviewed'] ?? 0);
		$stale = (int) ($row['stale'] ?? 0);

		return [
			'locale' => $locale,
			'label' => LocaleRegistry::getDisplayLabel($locale),
			'total' => $totalMessages,
			'translated' => $translated,
			'missing' => $missing,
			'reviewed' => $reviewed,
			'unreviewed' => max(0, $translated - $reviewed),
			'stale' => $stale,
			'translated_percent' => $this->_percent($translated, $totalMessages),
			'reviewed_percent' => $this->_percent($reviewed, $totalMessages),
			'status' => $totalMessages <= 0 ? 'empty' : ($missing > 0 || $stale > 0 ? 'needs_work' : 'ok'),
		];
	}

	private function _countMessages(): int
	{
		return (int) Db::instance()->query('SELECT COUNT(*) FROM i18n_messages')->fetchColumn();
	}

	/**
	 * @param list<array<string, mixed>> $summaries
	 */
	private function _coverageStatus(array $summaries): string
	{
		foreach ($summaries as $summary) {
			if (!in_array(($summary['status'] ?? ''), ['ok', 'empty'], true)) {
				return 'needs_work';
			}
		}

		return 'ok';
	}

	private function _percent(int $value, int $total): float
	{
		return $total > 0 ? round(($value / $total) * 100, 1) : 0.0;
	}
}
