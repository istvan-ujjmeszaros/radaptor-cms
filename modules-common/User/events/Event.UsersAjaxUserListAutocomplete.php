<?php

class EventUsersAjaxUserListAutocomplete extends AbstractEvent implements iBrowserEventDocumentable
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
			'event_name' => 'users_ajax_user_list.autocomplete',
			'group' => 'Admin AJAX',
			'name' => 'Autocomplete users',
			'summary' => 'Returns a lightweight user list for autocomplete widgets.',
			'description' => 'Filters users by the provided search term and returns a JSON structure suitable for autocomplete controls.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('term', 'query', 'string', false, 'Autocomplete search term.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns a JSON list of matching users.',
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
		$term = trim(urldecode((string) Request::_GET('term')), " +");

		$list = User::getUserListForSelect($term);

		echo json_encode($list);
	}
}
