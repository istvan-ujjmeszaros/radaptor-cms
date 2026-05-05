<?php

/**
 * jsTree AJAX loader for resource tree.
 *
 * Detects jsTree version from request parameters and returns
 * appropriately formatted JSON data.
 */
class EventJstreeResourcesAjaxLoad extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->inGroup(Usergroups::SYSTEMUSERGROUP_LOGGEDIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'jstree_resources_ajax.load',
			'group' => 'Admin AJAX',
			'name' => 'Load resource tree nodes',
			'summary' => 'Returns jsTree payload for the CMS resource tree.',
			'description' => 'Loads one resource-tree branch in jsTree-compatible JSON, with support for multiple request shapes.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('node_id', 'query', 'string', false, 'Preferred node identifier for jsTree 3.x style calls.'),
					BrowserEventDocumentationHelper::param('id', 'query', 'string', false, 'Fallback node identifier used by some jsTree flows.'),
					BrowserEventDocumentationHelper::param('id_prefix', 'query', 'string', false, 'Optional DOM id prefix for generated node ids.'),
					BrowserEventDocumentationHelper::param('shape_template', 'query', 'string', false, 'Optional response shape template override.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns jsTree node data for the requested resource-tree parent.',
			],
			'authorization' => [
				'visibility' => 'logged-in users',
				'description' => 'Requires membership in the logged-in system usergroup.',
			],
			'notes' => BrowserEventDocumentationHelper::lines(
				'Supports both node_id and id inputs to tolerate different jsTree client variants.'
			),
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		$id_prefix = Request::_GET('id_prefix', 'jstree_resources');
		$shape_template = Request::_GET('shape_template', null);
		$node_id = Request::_GET('node_id', Request::_GET('id', '#'));
		$is_root_request = $node_id === '#' || $node_id === 'root';

		try {
			$root_id = CmsSiteContext::getCurrentRootId();

			if ($root_id === null) {
				throw new RuntimeException('Root node not found');
			}

			if ($is_root_request) {
				$parent_node_id = 0;
				$root_data = ResourceTreeHandler::getResourceTreeEntryDataById($root_id);

				if (!is_array($root_data)) {
					throw new RuntimeException('Root node not found');
				}

				$root_data['_jstree_id'] = ResourceTreeHandler::JSTREE_SITE_ROOT_ID;
				$root_data['_jstree_data_node_id'] = 0;
				$raw_data = [$root_data];
				$parent_data = null;
			} elseif ($node_id === ResourceTreeHandler::JSTREE_SITE_ROOT_ID) {
				$parent_node_id = $root_id;
				$raw_data = ResourceTreeHandler::getResourceTree($parent_node_id);
				$parent_data = ResourceTreeHandler::getResourceTreeEntryDataById($parent_node_id);
			} else {
				$parent_node_id = JsTreeApiService::resolveParentNodeId($node_id);
				$raw_data = ResourceTreeHandler::getResourceTree($parent_node_id);
				$parent_data = ResourceTreeHandler::getResourceTreeEntryDataById($parent_node_id);
			}
		} catch (RuntimeException $exception) {
			ApiResponse::renderError('ROOT_RESOLUTION_FAILED', $exception->getMessage(), 500);

			return;
		}

		$response = JsTreeApiService::buildResponse(
			[JsTreeApiService::TEMPLATE_JSTREE_3],
			JsTreeApiService::TYPE_RESOURCES,
			$raw_data,
			[
				'parent_data' => $parent_data,
				'parent_node_id' => $parent_node_id,
				'id_prefix' => $id_prefix,
				'is_root_request' => $is_root_request,
			],
			$shape_template
		);

		ApiResponse::renderResponse($response);
	}
}
