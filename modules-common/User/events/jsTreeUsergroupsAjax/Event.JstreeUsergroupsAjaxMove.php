<?php

class EventJstreeUsergroupsAjaxMove extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_USERGROUPS_ADMIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		try {
			$id = Request::getRequired('id');
			$ref_node_id = Request::getRequired('ref');
			$position = Request::getRequired('position');
		} catch (RequestParamException $e) {
			ApiResponse::renderError($e->code_id, $e->getMessage(), 400);

			return;
		}

		$success = Usergroups::moveToPosition($id, $ref_node_id, $position);
		$data = Usergroups::getUsergroupValues($id);
		$parent_data = Usergroups::getUsergroupValues($data['parent_id']);

		$json_data = [
			'debug' => NestedSet::$debug,
			'data' => $data,
			'parent_data' => $parent_data,
			'parent_node' => (!empty($parent_data) ? 'usergroup' : 'root') . (!empty($parent_data) ? '_' . $data['parent_id'] : ''),
		];

		if ($success) {
			SystemMessages::_ok(t('user.usergroup.move_success', ['title' => $data['title']]));
		} else {
			SystemMessages::_error(t('user.usergroup.move_error') . '<br>' . print_r(NestedSet::$debug, true));
		}

		JsTreeApiService::renderMoveResponse($success, $json_data, ['debug' => NestedSet::$debug]);
	}
}
