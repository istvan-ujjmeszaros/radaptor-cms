<?php

class EventWidgetConnectionRemove extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->inGroup(Usergroups::SYSTEMUSERGROUP_LOGGEDIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		if (Widget::removeWidgetFromWebpage(Request::_GET('item_id', Request::DEFAULT_ERROR))) {
			SystemMessages::_ok(t('cms.widget_connection.removed'));
		}

		Kernel::redirectToReferer();
	}
}
