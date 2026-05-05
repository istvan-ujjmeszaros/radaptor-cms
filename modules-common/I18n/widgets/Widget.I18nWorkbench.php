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
		$selectedLocale = Request::_GET('locale', Kernel::getLocale());
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
		if (!class_exists('I18nCoverageService')) {
			return [];
		}

		return I18nCoverageService::summarize([
			'locales' => array_values(array_map(
				static fn (array $option): string => (string) $option['value'],
				$localeOptions
			)),
		]);
	}
}
