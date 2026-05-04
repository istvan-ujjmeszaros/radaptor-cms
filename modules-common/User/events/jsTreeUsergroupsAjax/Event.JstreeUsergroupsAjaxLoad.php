<?php

/**
 * jsTree AJAX loader for usergroups tree (browser view).
 *
 * Detects jsTree version from request parameters and returns
 * appropriately formatted JSON data.
 */
class EventJstreeUsergroupsAjaxLoad extends AbstractEvent implements iBrowserEventDocumentable
{
	private const int MAX_FULL_LOAD_NODES = 1000;

	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_USERGROUPS_ADMIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'jstree_usergroups_ajax.load',
			'group' => 'Admin AJAX',
			'name' => 'Load user groups tree nodes',
			'summary' => 'Returns jsTree payload for the user groups hierarchy.',
			'description' => 'Loads one user-groups-tree branch, or the complete expanded tree when requested, for browser-side jsTree rendering.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('node_id', 'query', 'string', false, 'Preferred node identifier for jsTree 3.x style calls.'),
					BrowserEventDocumentationHelper::param('id', 'query', 'string', false, 'Fallback node identifier used by some jsTree flows.'),
					BrowserEventDocumentationHelper::param('id_prefix', 'query', 'string', false, 'Optional DOM id prefix for generated node ids.'),
					BrowserEventDocumentationHelper::param('shape_template', 'query', 'string', false, 'Optional response shape template override.'),
					BrowserEventDocumentationHelper::param('load_all', 'query', 'boolean', false, 'When true on the root request, returns the full user groups tree with every branch opened unless the tree exceeds the server safety limit.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns jsTree node data for the requested user-groups-tree parent.',
			],
			'authorization' => [
				'visibility' => 'role:usergroups_admin',
				'description' => 'Requires the user groups admin role.',
			],
			'notes' => [],
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		$id_prefix = Request::_GET('id_prefix', 'jstree_usergroups');
		$shape_template = Request::_GET('shape_template', null);
		$node_id = Request::_GET('node_id', Request::_GET('id', '#'));
		$requested_load_all = in_array((string) Request::_GET('load_all', '0'), ['1', 'true', 'yes'], true);

		$parent_node_id = JsTreeApiService::resolveParentNodeId($node_id, 0);
		$load_all = $requested_load_all && $parent_node_id === 0;
		$meta = [];

		if ($load_all) {
			$raw_data = Usergroups::getFullUsergroupTree();

			if (count($raw_data) > self::MAX_FULL_LOAD_NODES) {
				$meta = [
					'full_load_degraded' => true,
					'full_load_limit' => self::MAX_FULL_LOAD_NODES,
					'node_count' => count($raw_data),
				];
				$load_all = false;
				$raw_data = Usergroups::getResourceTree($parent_node_id);
			}
		} else {
			$raw_data = Usergroups::getResourceTree($parent_node_id);
		}

		$response = JsTreeApiService::buildResponse(
			[JsTreeApiService::TEMPLATE_JSTREE_3],
			JsTreeApiService::TYPE_USERGROUPS,
			$raw_data,
			[
				'parent_node_id' => $parent_node_id,
				'id_prefix' => $id_prefix,
				'load_all' => $load_all,
			],
			$shape_template,
			$meta
		);

		ApiResponse::renderResponse($response);
	}
}
