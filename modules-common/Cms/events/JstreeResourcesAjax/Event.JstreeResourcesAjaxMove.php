<?php

class EventJstreeResourcesAjaxMove extends AbstractEvent
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

		//$movedPageData = ResourceHandler::getResourceDataById($id);
		//$refPageData = Webpage::getWebpageDataById($ref_node_id);

		$success = ResourceTreeHandler::moveResourceEntryToPosition($id, $ref_node_id, $position);
		$data = ResourceTreeHandler::getResourceTreeEntryDataById($id);
		$parent_data = ResourceTreeHandler::getResourceTreeEntryDataById($data['parent_id']);

		$json_data = [
			'debug' => NestedSet::$debug,
			'data' => $data,
			'parent_data' => $parent_data,
			'parent_node' => (is_array($parent_data) ? $parent_data['node_type'] : 'root') . (is_array($parent_data) ? '_' . $data['parent_id'] : ''),
		];

		if ($success) {
			SystemMessages::_ok(t('cms.resource.moved') . '<br><i>(' . $data['resource_name'] . ')</i>');
			// HX-Trigger for htmx clients
			header('HX-Trigger: ' . json_encode([
				'resourceTreeMoved' => [
					'nodeId' => (string) $id,
					'success' => true,
				],
			]));
		} else {
			SystemMessages::_error(t('cms.resource.move_error') . '<br>' . print_r(NestedSet::$debug, true));
			header('HX-Trigger: ' . json_encode([
				'resourceTreeError' => [
					'message' => t('cms.resource.move_failed'),
				],
			]));
		}

		JsTreeApiService::renderMoveResponse($success, $json_data, ['debug' => NestedSet::$debug]);
	}
}
