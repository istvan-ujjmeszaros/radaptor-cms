<?php

class EventWidgetDescription extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return PolicyDecision::allow();
	}

	public function run(): void
	{
		$widget_name = urldecode((string) Request::_GET('id', Request::DEFAULT_ERROR));

		$description = Widget::getWidgetDescription($widget_name);

		if (trim(strip_tags($description)) == '') {
			echo '<i>' . htmlspecialchars(t('common.no_description'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</i>';
		} else {
			echo $description;
		}
	}
}
