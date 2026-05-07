<?php

/**
 * jsTree AJAX loader for admin menu tree.
 *
 * Detects jsTree version from request parameters and returns
 * appropriately formatted JSON data.
 */
class EventJstreeAdminMenuAjaxLoad extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$id_prefix = Request::_GET('id_prefix', 'jstree_adminmenu');
		$shape_template = Request::_GET('shape_template', null);

		// Detect jsTree version and get parent node ID
		// jsTree 1.x sends 'parent_id', jsTree 3.x sends 'id' (with '#' for root)
		$parent_node_id = Request::_GET('parent_id', Request::_GET('id'));

		if ($parent_node_id === 'root' || $parent_node_id === '#') {
			$parent_node_id = 0;
		} elseif (filter_var($parent_node_id, FILTER_VALIDATE_INT) === false) {
			ApiResponse::renderError('INVALID_PARENT_ID', t('cms.tree.invalid_parent_id'), 400);

			return;
		} else {
			$parent_node_id = (int) $parent_node_id;
		}

		// Get raw tree data
		$raw_data = AdminMenu::getMenuTree($parent_node_id);

		$response = JsTreeApiService::buildResponse(
			[JsTreeApiService::TEMPLATE_JSTREE_3],
			JsTreeApiService::TYPE_ADMINMENU,
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
