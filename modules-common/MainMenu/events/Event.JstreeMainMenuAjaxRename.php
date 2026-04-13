<?php

class EventJstreeMainMenuAjaxRename extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->inGroup(Usergroups::SYSTEMUSERGROUP_LOGGEDIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		echo json_encode(t('common.renamed'));
	}
}
