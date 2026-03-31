<?php

class EventJstreeMainMenuAjaxLoad extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->inGroup(Usergroups::SYSTEMUSERGROUP_LOGGEDIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$allowed_templates = [
			JsTreeApiService::TEMPLATE_JSTREE_1,
			JsTreeApiService::TEMPLATE_JSTREE_3,
		];
		$shape_template = Request::_GET('shape_template', null);

		// jsTree 1.x sends 'parent_id', jsTree 3.x sends 'id' (with '#' for root)
		$parent_id = Request::_GET('parent_id', Request::_GET('id'));

		if ($parent_id === 'root' || $parent_id === '#') {
			$parent_node_id = 0;
		} elseif (filter_var($parent_id, FILTER_VALIDATE_INT) === false) {
			ApiResponse::renderError('INVALID_PARENT_ID', t('cms.tree.invalid_parent_id'), 400);

			return;
		} else {
			$parent_node_id = (int) $parent_id;
		}

		$raw_data = MainMenu::getMenuTree($parent_node_id);

		$context = [
			'parent_node_id' => $parent_node_id,
			'id_prefix' => Request::_GET('id_prefix', ''),
		];

		$response = JsTreeApiService::buildResponse($allowed_templates, JsTreeApiService::TYPE_MAINMENU, $raw_data, $context, $shape_template);
		ApiResponse::renderResponse($response);
	}
}
