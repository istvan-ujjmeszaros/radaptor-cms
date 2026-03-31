<?php

class EventUsersUserListAjaxLoad extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_USERS_ADMIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$userList = User::getUserList();

		// DataTables 2.x uses 'data' instead of 'aaData'
		$output = ['data' => []];

		foreach ($userList as $row) {
			$output['data'][] = [
				sprintf('%04d', $row['user_id']),
				$row['username'],
				$row['user_id'],
			];
		}

		ApiResponse::renderSuccess($output);
	}
}
