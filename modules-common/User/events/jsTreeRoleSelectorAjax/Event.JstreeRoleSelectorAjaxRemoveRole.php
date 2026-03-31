<?php

class EventJstreeRoleSelectorAjaxRemoveRole extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return ($policyContext->principal->hasRole(RoleList::ROLE_USERS_ROLE_ADMIN) || $policyContext->principal->hasRole(RoleList::ROLE_USERGROUPS_ROLE_ADMIN))
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		try {
			$for_type = Request::getRequired('for_type');
			$for_id = (int) Request::getRequired('for_id');
			$role_id = (int) Request::getRequired('role_id');
		} catch (RequestParamException $e) {
			ApiResponse::renderError($e->code_id, $e->getMessage(), 400);

			return;
		}

		if (!in_array($for_type, [
			'user',
			'usergroup',
		])) {
			ApiResponse::renderError('INVALID_ROLE_TYPE', t('user.role.invalid_type', ['type' => $for_type]), 400);

			return;
		}

		$role_data = Roles::getRoleValues($role_id);

		if (is_null($role_data)) {
			ApiResponse::renderError('ROLE_NOT_FOUND', t('user.role.not_found', ['id' => $role_id]), 404);

			return;
		}

		$success = false;

		switch ($for_type) {
			case 'user':

				$user_data = User::getUserFromId($for_id);

				if (empty($user_data)) {
					ApiResponse::renderError('USER_NOT_FOUND', t('user.error_not_found_with_id', ['id' => $for_id]), 404);

					return;
				}

				$success = Roles::removeFromUser($role_id, $for_id);

				if ($success) {
					SystemMessages::_ok(t('user.role_assignment.removed', ['role' => $role_data['title'], 'target' => $user_data['username']]));
				} else {
					SystemMessages::_error(t('user.role_assignment.error_remove', ['role' => $role_data['title'], 'target' => $user_data['username']]));
				}

				break;

			case 'usergroup':

				$usergroup_data = Usergroups::getUsergroupValues($for_id);

				if (empty($usergroup_data)) {
					ApiResponse::renderError('USERGROUP_NOT_FOUND', t('user.usergroup.not_found', ['id' => $for_id]), 404);

					return;
				}

				$success = Roles::removeFromUsergroup($role_id, $for_id);

				if ($success) {
					SystemMessages::_ok(t('user.role_assignment.removed', ['role' => $role_data['title'], 'target' => $usergroup_data['title']]));
				} else {
					SystemMessages::_error(t('user.role_assignment.error_remove', ['role' => $role_data['title'], 'target' => $usergroup_data['title']]));
				}

				break;

			default:

				ApiResponse::renderError('INVALID_ROLE_TYPE', t('user.role.invalid_type', ['type' => $for_type]), 400);

				return;
		}

		if ($success) {
			ApiResponse::renderSuccess();
		} else {
			ApiResponse::renderErrorObj(
				new ApiError('OPERATION_FAILED', t('user.role_assignment.error_remove_generic')),
				400
			);
		}
	}
}
