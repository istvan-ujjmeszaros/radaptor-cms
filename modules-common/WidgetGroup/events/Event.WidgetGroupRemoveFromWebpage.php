<?php

class EventWidgetGroupRemoveFromWebpage extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->inGroup(Usergroups::SYSTEMUSERGROUP_LOGGEDIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$beginning_connection_id = Request::_GET('item_id', Request::DEFAULT_ERROR);
		$end_connection_id = WidgetGroup::getNextEndConnectionId($beginning_connection_id);

		$success_beginning = Widget::removeWidgetFromWebpage($beginning_connection_id);

		if ($end_connection_id) {
			$success_end = Widget::removeWidgetFromWebpage($end_connection_id);

			if ($success_beginning && $success_end) {
				SystemMessages::_ok(t('cms.widget_group.removed'));
			} else {
				if ($success_beginning) {
					SystemMessages::_error(t('cms.widget_group.end_delete_error'));
				} elseif ($success_end) {
					SystemMessages::_error(t('cms.widget_group.begin_delete_error'));
				} else {
					SystemMessages::_error(t('cms.widget_group.both_delete_error'));
				}
			}
		} else {
			if ($success_beginning) {
				SystemMessages::_error(t('cms.widget_group.end_missing_removed_beginning'));
			} else {
				SystemMessages::_error(t('cms.widget_group.end_missing_delete_error'));
			}
		}

		Kernel::redirectToReferer();
	}
}
