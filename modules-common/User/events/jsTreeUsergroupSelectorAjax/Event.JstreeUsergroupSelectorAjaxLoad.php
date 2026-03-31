<?php

/**
 * jsTree AJAX loader for usergroup selector tree with checkboxes.
 *
 * Detects jsTree version from request parameters and returns
 * appropriately formatted JSON data.
 */
class EventJstreeUsergroupSelectorAjaxLoad extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return ($policyContext->principal->hasRole(RoleList::ROLE_USERS_ADMIN) || $policyContext->principal->hasRole(RoleList::ROLE_USERS_USERGROUP_ADMIN))
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		try {
			$for_id = (int) Request::getRequired('for_id');
		} catch (RequestParamException $e) {
			ApiResponse::renderError($e->code_id, $e->getMessage(), 400);

			return;
		}

		$shape_template = Request::_GET('shape_template', null);
		$node_id = Request::_GET('node_id', Request::_GET('id', '#'));
		$parent_node_id = JsTreeApiService::resolveParentNodeId($node_id, 0);

		// Get raw tree data
		$raw_data = Usergroups::getResourceTree($parent_node_id);

		$response = JsTreeApiService::buildResponse(
			[JsTreeApiService::TEMPLATE_JSTREE_3],
			JsTreeApiService::TYPE_USERGROUP_SELECTOR,
			$raw_data,
			[
				'for_id' => $for_id,
			],
			$shape_template
		);

		ApiResponse::renderResponse($response);
	}
}
