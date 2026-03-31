<?php

class EventWidgetConnectionAdd extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->inGroup(Usergroups::SYSTEMUSERGROUP_LOGGEDIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$widget_name = Request::_POST('widget_name', '');

		if ($widget_name === '') {
			SystemMessages::_warning(t('cms.widget_connection.select_widget_type'));
			Kernel::redirectToReferer();
		}

		if (!Widget::checkWidgetExists($widget_name)) {
			Kernel::abort(__FILE__ . ': line ' . __LINE__);
		}

		$slot_name = Request::_GET('slot_name', Request::DEFAULT_ERROR);

		if (Widget::assignWidgetToWebpage(Request::_GET('pageid', Request::DEFAULT_ERROR), $slot_name, $widget_name, Request::_GET('seq'))) {
			SystemMessages::_ok(t('cms.widget_connection.added'));
		} else {
			SystemMessages::_error(t('cms.widget_connection.duplicate_not_allowed'));
		}

		Kernel::redirectToReferer();
	}
}
