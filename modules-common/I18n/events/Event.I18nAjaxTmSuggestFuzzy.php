<?php

class EventI18nAjaxTmSuggestFuzzy extends AbstractEvent implements iBrowserEventDocumentable
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
			'event_name' => 'i18n_ajax.tm-suggest-fuzzy',
			'group' => 'I18n',
			'name' => 'Load fuzzy TM suggestions',
			'summary' => 'Returns fuzzy translation-memory suggestions based on source text similarity.',
			'description' => 'Looks up fuzzy TM matches using the source text of the requested message and the target locale.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('domain', 'query', 'string', true, 'Translation domain.'),
					BrowserEventDocumentationHelper::param('key', 'query', 'string', true, 'Translation key.'),
					BrowserEventDocumentationHelper::param('message_context', 'query', 'string', false, 'Optional message context.'),
					BrowserEventDocumentationHelper::param('locale', 'query', 'string', true, 'Target locale code.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns a JSON array of fuzzy TM suggestions.',
			],
			'authorization' => [
				'visibility' => 'role:i18n_translator',
				'description' => 'Requires the i18n translator role.',
			],
			'notes' => BrowserEventDocumentationHelper::lines(
				'This endpoint derives source text first, then performs fuzzy matching against TM entries.'
			),
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		$domain = Request::_GET('domain', '');
		$key = Request::_GET('key', '');
		$context = Request::_GET('message_context', '');
		$locale = LocaleService::tryCanonicalize((string) Request::_GET('locale', '')) ?? '';
		$sourceText = I18nWorkbench::getSourceText($domain, $key, $context) ?? '';
		$suggestions = I18nTm::getFuzzySuggestions($sourceText, $locale, $domain, $context);

		header('Content-Type: application/json');
		echo json_encode(['suggestions' => $suggestions], JSON_UNESCAPED_UNICODE);
	}
}
