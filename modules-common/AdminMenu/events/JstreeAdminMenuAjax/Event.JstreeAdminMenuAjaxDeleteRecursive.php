<?php

class EventJstreeAdminMenuAjaxDeleteRecursive extends AbstractEvent
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
		} catch (RequestParamException $e) {
			ApiResponse::renderError($e->code_id, $e->getMessage(), 400);

			return;
		}

		$data = AdminMenu::factory($id);

		if (is_array($data)) {
			if ($data['node_type'] != 'root') {
				$success = AdminMenu::deleteRecursive($id);
			} else {
				$success = false;
			}

			if ($success) {
				SystemMessages::addSystemMessage(t('cms.menu.deleted', ['name' => $data['node_name']]));
			} else {
				SystemMessages::_error(t('cms.menu.delete_forbidden', ['name' => $data['node_name']]));
			}
		} else {
			$success = false;
			SystemMessages::_error(t('cms.menu.delete_unknown_error'));
		}

		if ($success) {
			$json_data = [
				'data' => $data,
				'parent_data' => $data['parent'],
				'parent_node' => (is_object($data['parent']) ? $data['parent']->node_type : 'root') . (is_object($data['parent']) && isset($data['parent_id']) ? '_' . $data['parent_id'] : ''),
			];

			// Trigger htmx event for Stimulus controller to remove node from tree
			header('HX-Trigger: ' . json_encode(['adminmenuTreeDeleted' => ['nodeId' => $id]]));
		} else {
			$json_data = [];

			// Trigger error event
			header('HX-Trigger: ' . json_encode(['adminmenuTreeError' => ['message' => t('cms.menu.delete_failed')]]));
		}

		if ($success) {
			ApiResponse::renderSuccess($json_data);
		} else {
			ApiResponse::renderErrorObj(new ApiError('OPERATION_FAILED', t('cms.menu.delete_failed')), 400);
		}
	}
}
