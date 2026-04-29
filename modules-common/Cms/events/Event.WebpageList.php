<?php

declare(strict_types=1);

class EventWebpageList extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return McpCmsAuthoringAuthorization::resourcePath(
			(string) Request::_GET('path', '/'),
			ResourceAcl::_ACL_LIST,
			$policyContext
		);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'webpage.list',
			'group' => 'CMS Authoring',
			'name' => 'List webpages',
			'summary' => 'Lists webpages under a resource path.',
			'description' => 'Returns JSON-shaped webpage specs for the selected subtree.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('path', 'query', 'string', false, 'Base resource path. Defaults to /.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns base path, count, and webpage specs visible to the current user.',
			],
			'authorization' => [
				'visibility' => 'resource ACL',
				'description' => 'Requires list permission on the base resource and filters results by view permission.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.webpage.list',
				'risk' => 'read',
			],
			'notes' => [],
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		$base_path = CLIWebpageHelper::normalizePath((string) Request::_GET('path', '/'));
		$pages = CLIWebpageHelper::getWebpagesUnderPath($base_path);
		$result_pages = [];

		foreach ($pages as $page) {
			$page_id = (int) $page['node_id'];

			if (!ResourceAcl::canAccessResource($page_id, ResourceAcl::_ACL_VIEW)) {
				continue;
			}

			$result_pages[] = CmsResourceSpecService::exportWebpageSpec(ResourceTreeHandler::getPathFromId($page_id));
		}

		ApiResponse::renderSuccess([
			'base_path' => $base_path,
			'count' => count($result_pages),
			'pages' => $result_pages,
		]);
	}
}
