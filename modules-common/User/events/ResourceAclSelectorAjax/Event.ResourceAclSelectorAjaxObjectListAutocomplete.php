<?php

class EventResourceAclSelectorAjaxObjectListAutocomplete extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return ($policyContext->principal->hasRole(RoleList::ROLE_ACL_VIEWER) || $policyContext->principal->hasRole(RoleList::ROLE_SYSTEM_DEVELOPER))
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$term = trim(urldecode((string) Request::_GET('term')), " +");

		$list = ResourceAcl::getObjectListForSelect($term);

		echo json_encode($list);
	}
}
