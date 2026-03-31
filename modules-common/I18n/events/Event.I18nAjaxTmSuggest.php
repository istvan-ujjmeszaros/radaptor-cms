<?php

class EventI18nAjaxTmSuggest extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return Roles::hasRole(RoleList::ROLE_I18N_TRANSLATOR)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
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
