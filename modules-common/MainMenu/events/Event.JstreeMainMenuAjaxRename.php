<?php

class EventJstreeMainMenuAjaxRename extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_CONTENT_ADMIN)
			|| $policyContext->principal->hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		echo json_encode(t('common.renamed'));
	}
}
