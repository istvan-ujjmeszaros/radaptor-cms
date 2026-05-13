<?php

class EventI18nAjaxLoad extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return Roles::hasRole(RoleList::ROLE_I18N_TRANSLATOR)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'i18n_ajax.load',
			'group' => 'I18n',
			'name' => 'Load i18n workbench rows',
			'summary' => 'Returns paginated translation rows for the i18n workbench table.',
			'description' => 'Loads translated rows for one locale with optional domain/search filters and DataTables-style paging parameters.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('locale', 'query', 'string', false, 'Locale code to load, defaults to en-US.'),
					BrowserEventDocumentationHelper::param('start', 'query', 'int', false, 'Result offset for paging.'),
					BrowserEventDocumentationHelper::param('length', 'query', 'int', false, 'Page size for paging.'),
					BrowserEventDocumentationHelper::param('draw', 'query', 'int', false, 'Opaque draw token echoed back to the caller.'),
					BrowserEventDocumentationHelper::param('domain', 'query', 'string', false, 'Optional domain filter.'),
					BrowserEventDocumentationHelper::param('search', 'query', 'string', false, 'Optional free-text filter.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns a paginated JSON payload for the workbench table.',
			],
			'authorization' => [
				'visibility' => 'role:i18n_translator',
				'description' => 'Requires the i18n translator role.',
			],
			'notes' => [],
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		$locale  = LocaleService::tryCanonicalize((string) Request::_GET('locale', LocaleService::getDefaultLocale())) ?? LocaleService::getDefaultLocale();
		$start   = (int) Request::_GET('start', 0);
		$length  = (int) Request::_GET('length', 25);
		$draw    = (int) Request::_GET('draw', 1);

		$filters = [
			'domain' => Request::_GET('domain', ''),
			'search' => Request::_GET('search', ''),
		];

		$result = I18nWorkbench::getTranslations($locale, $filters, $start, $length);
		$result['draw'] = $draw;

		header('Content-Type: application/json');
		echo json_encode($result, JSON_UNESCAPED_UNICODE);
	}
}
