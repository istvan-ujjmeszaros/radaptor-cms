<?php

/**
 * jsTree AJAX loader for roles tree (browser view).
 *
 * Detects jsTree version from request parameters and returns
 * appropriately formatted JSON data.
 */
class EventJstreeRolesAjaxLoad extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return ($policyContext->principal->hasRole(RoleList::ROLE_ROLES_ADMIN) || $policyContext->principal->hasRole(RoleList::ROLE_ROLES_VIEWER))
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$id_prefix = Request::_GET('id_prefix', 'jstree_roles');
		$shape_template = Request::_GET('shape_template', null);
		$node_id = Request::_GET('node_id', Request::_GET('id', '#'));

		$parent_node_id = JsTreeApiService::resolveParentNodeId($node_id, 0);

		// Get raw tree data
		$raw_data = Roles::getRoleTree($parent_node_id);

		$response = JsTreeApiService::buildResponse(
			[JsTreeApiService::TEMPLATE_JSTREE_3],
			JsTreeApiService::TYPE_ROLES,
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
