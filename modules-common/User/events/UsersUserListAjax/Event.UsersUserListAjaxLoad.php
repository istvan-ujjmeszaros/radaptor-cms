<?php

class EventUsersUserListAjaxLoad extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_USERS_ADMIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'users_user_list_ajax.load',
			'group' => 'Admin AJAX',
			'name' => 'Load user table rows',
			'summary' => 'Returns user rows for the admin user list table.',
			'description' => 'Loads all users and returns a DataTables-friendly JSON payload.',
			'request' => [
				'method' => 'GET',
				'params' => [],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns JSON with a data array of user rows.',
			],
			'authorization' => [
				'visibility' => 'role:users_admin',
				'description' => 'Requires the users admin role.',
			],
			'notes' => [],
			'side_effects' => [],
		];
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
