<?php

class EventJstreeMainMenuAjaxMove extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->inGroup(Usergroups::SYSTEMUSERGROUP_LOGGEDIN)
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

		$success = MainMenu::moveToPosition($id, $ref_node_id, $position);

		$data = MainMenu::factory($id);

		$json_data = [
			'debug' => NestedSet::$debug,
			'data' => $data,
			'parent_data' => $data['parent'],
			'parent_node' => (is_object($data['parent']) ? $data['parent']->node_type : 'root') . (is_object($data['parent']) && isset($data['parent_id']) ? '_' . $data['parent_id'] : ''),
		];

		if ($success) {
			SystemMessages::_ok(t('cms.menu.move_success', ['name' => $data['node_name']]));
		} else {
			SystemMessages::_error(t('cms.menu.move_error') . '<br>' . print_r(NestedSet::$debug, true));
		}

		JsTreeApiService::renderMoveResponse($success, $json_data, ['debug' => NestedSet::$debug]);
	}
}
