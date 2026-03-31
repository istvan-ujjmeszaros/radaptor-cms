<?php

class EventWidgetConnectionSwap extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->inGroup(Usergroups::SYSTEMUSERGROUP_LOGGEDIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$table = 'widget_connections';

		if (DbHelper::swapHelper($table, Request::_GET('item_id', Request::DEFAULT_ERROR), Request::_GET('swap_id', Request::DEFAULT_ERROR))) {
			SystemMessages::_ok(t('cms.widget_connection.moved'));
		}

		Kernel::redirectToReferer();
	}
}
