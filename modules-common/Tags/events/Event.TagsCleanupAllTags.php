<?php

class EventTagsCleanupAllTags extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return PolicyDecision::allow();
	}

	public function run(): void
	{
		echo "cleaning up all tag names...";

		EntityTag::cleanupAllTags();
	}
}
