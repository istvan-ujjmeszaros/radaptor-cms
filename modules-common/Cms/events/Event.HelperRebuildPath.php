<?php

class EventHelperRebuildPath extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return PolicyDecision::allow();
	}

	public function run(): never
	{
		var_dump(ResourceTreeHandler::rebuildPath());

		exit;
	}
}
