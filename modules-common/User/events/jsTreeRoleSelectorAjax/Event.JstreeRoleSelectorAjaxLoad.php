<?php

/**
 * jsTree AJAX loader for role selector tree with checkboxes.
 *
 * Detects jsTree version from request parameters and returns
 * appropriately formatted JSON data.
 */
class EventJstreeRoleSelectorAjaxLoad extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return ($policyContext->principal->hasRole(RoleList::ROLE_USERS_ROLE_ADMIN) || $policyContext->principal->hasRole(RoleList::ROLE_USERGROUPS_ROLE_ADMIN))
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		try {
			$for_type = Request::getRequired('for_type');
			$for_id = (int) Request::getRequired('for_id');
		} catch (RequestParamException $e) {
			ApiResponse::renderError($e->code_id, $e->getMessage(), 400);

			return;
		}

		if (!in_array($for_type, ['user', 'usergroup'], true)) {
			ApiResponse::renderError('INVALID_ROLE_TYPE', t('user.role.invalid_type', ['type' => $for_type]), 400);

			return;
		}
		$shape_template = Request::_GET('shape_template', null);

		// Detect jsTree version and get parent node ID
		// jsTree 1.x sends 'node_id', jsTree 3.x sends 'id' (with '#' for root)
		$id = Request::_GET('node_id', Request::_GET('id', '#'));
		$parent_node_id = ($id === '#' || $id === 'root') ? 0 : (int) $id;

		// Get raw tree data
		$raw_data = Roles::getRoleTree($parent_node_id);

		$response = JsTreeApiService::buildResponse(
			[JsTreeApiService::TEMPLATE_JSTREE_3],
			JsTreeApiService::TYPE_ROLE_SELECTOR,
			$raw_data,
			[
				'for_type' => $for_type,
				'for_id' => $for_id,
			],
			$shape_template
		);

		ApiResponse::renderResponse($response);
	}
}
