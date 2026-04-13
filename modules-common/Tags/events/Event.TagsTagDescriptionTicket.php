<?php

class EventTagsTagDescriptionTicket extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return PolicyDecision::allow();
	}

	public function run(): void
	{
		EventTagsTagDescription::renderTagDescription(
			'tracker_ticket',
			urldecode((string) Request::_GET('item_id', Request::DEFAULT_ERROR))
		);
	}
}
