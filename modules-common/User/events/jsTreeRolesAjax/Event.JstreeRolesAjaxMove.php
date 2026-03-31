<?php

class EventJstreeRolesAjaxMove extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_ROLES_ADMIN)
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

		$success = Roles::moveToPosition($id, $ref_node_id, $position);
		$data = Roles::getRoleValues($id);
		$parent_data = Roles::getRoleValues($data['parent_id']);

		$json_data = [
			'debug' => NestedSet::$debug,
			'data' => $data,
			'parent_data' => $parent_data,
			'parent_node' => (is_array($parent_data) ? 'role' : 'root') . (is_array($parent_data) ? '_' . $data['parent_id'] : ''),
		];

		if ($success) {
			SystemMessages::_ok(t('user.role.move_success', ['title' => $data['title']]));
		} else {
			SystemMessages::_error(t('user.role.move_error') . '<br>' . print_r(NestedSet::$debug, true));
		}

		JsTreeApiService::renderMoveResponse($success, $json_data, ['debug' => NestedSet::$debug]);
	}
}
