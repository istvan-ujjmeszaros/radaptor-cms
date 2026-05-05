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

		$result = ResourceTreeHandler::moveResourceEntryToPositionResult($id, $ref_node_id, $position);
		$success = $result->ok;

		$data = ResourceTreeHandler::getResourceTreeEntryDataById($id);
		$parent_data = is_array($data) ? ResourceTreeHandler::getResourceTreeEntryDataById($data['parent_id']) : null;
		$error_message = $result->error?->message ?? t('cms.resource.move_failed');

		$json_data = [
			'debug' => NestedSet::$debug,
			'data' => $data,
			'parent_data' => $parent_data,
			'parent_node' => (is_array($parent_data) && is_array($data) ? $parent_data['node_type'] : 'root') . (is_array($parent_data) && is_array($data) ? '_' . $data['parent_id'] : ''),
		];

		if ($success) {
			header('HX-Trigger: ' . json_encode([
				'resourceTreeMoved' => [
					'nodeId' => (string) $id,
					'success' => true,
				],
			]));
		} else {
			header('HX-Trigger: ' . json_encode([
				'resourceTreeError' => [
					'message' => $error_message,
				],
			]));
		}

		if ($success) {
			ApiResponse::renderSuccess($json_data);

			return;
		}

		ApiResponse::renderErrorObj(
			$result->error ?? new ApiError('RESOURCE_MOVE_FAILED', t('cms.resource.move_failed')),
			400,
			['debug' => NestedSet::$debug, 'message' => $error_message]
		);
	}
}
