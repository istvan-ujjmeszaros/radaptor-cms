<?php

/**
 * jsTree AJAX loader for usergroups tree (browser view).
 *
 * Detects jsTree version from request parameters and returns
 * appropriately formatted JSON data.
 */
class EventJstreeUsergroupsAjaxLoad extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_USERGROUPS_ADMIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$id_prefix = Request::_GET('id_prefix', 'jstree_usergroups');
		$shape_template = Request::_GET('shape_template', null);

		// Detect jsTree version and get parent node ID
		// jsTree 1.x sends 'node_id', jsTree 3.x sends 'id' (with '#' for root)
		$parent_node_id = Request::_GET('node_id', Request::_GET('id'));

		if ($parent_node_id === 'root' || $parent_node_id === '#') {
			$parent_node_id = 0;
		} else {
			$parent_node_id = (int) $parent_node_id;
		}

		// Get raw tree data
		$raw_data = Usergroups::getResourceTree($parent_node_id);

		$response = JsTreeApiService::buildResponse(
			[JsTreeApiService::TEMPLATE_JSTREE_3],
			JsTreeApiService::TYPE_USERGROUPS,
			$raw_data,
			[
				'parent_node_id' => $parent_node_id,
				'id_prefix' => $id_prefix,
			],
			$shape_template
		);

		ApiResponse::renderResponse($response);
	}
}
