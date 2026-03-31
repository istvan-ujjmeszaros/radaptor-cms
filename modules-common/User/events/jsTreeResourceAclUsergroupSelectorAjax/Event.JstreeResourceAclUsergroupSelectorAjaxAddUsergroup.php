<?php

class EventJstreeResourceAclUsergroupSelectorAjaxAddUsergroup extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		try {
			$resource_id = (int) Request::getRequired('for_id');
			$usergroup_id = (int) Request::getRequired('usergroup_id');
		} catch (RequestParamException $e) {
			ApiResponse::renderError($e->code_id, $e->getMessage(), 400);

			return;
		}

		$usergroup_data = Usergroups::getUsergroupValues($usergroup_id);

		if (empty($usergroup_data)) {
			ApiResponse::renderError('USERGROUP_NOT_FOUND', t('user.usergroup.not_found', ['id' => $usergroup_id]), 400);

			return;
		}

		$resource_data = ResourceTreeHandler::getResourceTreeEntryDataById($resource_id);

		if (is_null($resource_data)) {
			ApiResponse::renderError('RESOURCE_NOT_FOUND', t('cms.resource_acl.resource_not_found', ['id' => $resource_id]), 400);

			return;
		}

		$success = ResourceTreeHandler::assignToUsergroup($usergroup_id, $resource_id);
		$target = $resource_data['path'] . $resource_data['resource_name'];

		if ($success) {
			SystemMessages::_ok(t('cms.resource_acl.usergroup_assignment.added', ['group' => $usergroup_data['title'], 'target' => $target]));
		} else {
			SystemMessages::_error(t('cms.resource_acl.usergroup_assignment.error_add', ['group' => $usergroup_data['title'], 'target' => $target]));
		}

		$json_data = ['success' => $success !== false];

		if ($json_data['success']) {
			ApiResponse::renderSuccess();
		} else {
			ApiResponse::renderErrorObj(new ApiError('OPERATION_FAILED', t('cms.resource_acl.usergroup_assignment.error_add_generic')), 400);
		}
	}
}
