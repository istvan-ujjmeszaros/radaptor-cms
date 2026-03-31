<?php

class EventI18nAjaxLoad extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return Roles::hasRole(RoleList::ROLE_I18N_TRANSLATOR)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$locale  = Request::_GET('locale', 'en_US');
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
