<?php

/**
 * jsTree AJAX loader for resource tree.
 *
 * Detects jsTree version from request parameters and returns
 * appropriately formatted JSON data.
 */
class EventJstreeResourcesAjaxLoad extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->inGroup(Usergroups::SYSTEMUSERGROUP_LOGGEDIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$id_prefix = Request::_GET('id_prefix', 'jstree_resources');
		$shape_template = Request::_GET('shape_template', null);
		$node_id = Request::_GET('node_id', Request::_GET('id', '#'));

		try {
			$parent_node_id = JsTreeApiService::resolveParentNodeId(
				node_id: $node_id,
				root_resolver: function (): int {
					$root = DbHelper::selectOne('resource_tree', ['node_type' => 'root']);

					if (is_null($root)) {
						ApiResponse::renderError('ROOT_NOT_FOUND', t('cms.resource_browser.root_not_found'), 500);

						throw new RuntimeException('Root node not found');
					}

					return (int) $root['parent_id'];
				}
			);
		} catch (RuntimeException) {
			return;
		}

		// Get raw tree data
		$raw_data = ResourceTreeHandler::getResourceTree($parent_node_id);
		$parent_data = ResourceTreeHandler::getResourceTreeEntryDataById($parent_node_id);

		$response = JsTreeApiService::buildResponse(
			[JsTreeApiService::TEMPLATE_JSTREE_3],
			JsTreeApiService::TYPE_RESOURCES,
			$raw_data,
			[
				'parent_data' => $parent_data,
				'id_prefix' => $id_prefix,
			],
			$shape_template
		);

		ApiResponse::renderResponse($response);
	}
}
