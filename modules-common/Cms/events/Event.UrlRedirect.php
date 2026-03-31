<?php

class EventUrlRedirect extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return PolicyDecision::allow();
	}

	public function run(): void
	{
		$id = Request::_GET("id", Request::DEFAULT_ERROR);

		Url::redirect(Url::getSeoUrl($id));
	}
}
