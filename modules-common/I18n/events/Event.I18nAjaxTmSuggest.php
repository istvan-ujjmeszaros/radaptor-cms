<?php

class EventI18nAjaxTmSuggest extends AbstractEvent implements iBrowserEventDocumentable
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
			'event_name' => 'i18n_ajax.tm-suggest',
			'group' => 'I18n',
			'name' => 'Load exact TM suggestions',
			'summary' => 'Returns translation-memory suggestions for one message signature.',
			'description' => 'Looks up matching translation-memory entries for the provided domain/key/context/locale combination.',
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
				'description' => 'Returns a JSON array of TM suggestions.',
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
		$domain  = Request::_GET('domain', '');
		$key     = Request::_GET('key', '');
		$context = Request::_GET('message_context', '');
		$locale  = Request::_GET('locale', '');

		$suggestions = I18nWorkbench::getTmSuggestions($domain, $key, $context, $locale);

		header('Content-Type: application/json');
		echo json_encode(['suggestions' => $suggestions], JSON_UNESCAPED_UNICODE);
	}
}
