<?php

class EventJstreeUsergroupSelectorAjaxAddUsergroup extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return ($policyContext->principal->hasRole(RoleList::ROLE_USERS_ADMIN) || $policyContext->principal->hasRole(RoleList::ROLE_USERS_USERGROUP_ADMIN))
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		try {
			$user_id = (int) Request::getRequired('for_id');
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

		$user_data = User::getUserFromId($user_id);

		if (empty($user_data)) {
			ApiResponse::renderError('USER_NOT_FOUND', t('user.error_not_found_with_id', ['id' => $user_id]), 400);

			return;
		}

		$success = Usergroups::assignToUser($usergroup_id, $user_id);

		if ($success) {
			SystemMessages::_ok(t('user.usergroup_assignment.added', ['group' => $usergroup_data['title'], 'user' => $user_data['username']]));
		} else {
			SystemMessages::_error(t('user.usergroup_assignment.error_add', ['group' => $usergroup_data['title'], 'user' => $user_data['username']]));
		}

		if ($success) {
			ApiResponse::renderSuccess();
		} else {
			ApiResponse::renderErrorObj(
				new ApiError('OPERATION_FAILED', t('user.usergroup_assignment.error_add_generic')),
				400
			);
		}
	}
}
