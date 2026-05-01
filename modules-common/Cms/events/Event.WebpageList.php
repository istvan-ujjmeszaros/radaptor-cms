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
		$base_path = CmsAuthoringQueryHelper::normalizePath((string) Request::_GET('path', '/'));
		$result_pages = [];

		try {
			foreach (CmsAuthoringQueryHelper::getWebpagesUnderPath($base_path) as $page) {
				$page_id = (int) $page['node_id'];

				if (!ResourceAcl::canAccessResource($page_id, ResourceAcl::_ACL_VIEW)) {
					continue;
				}

				$page_path = Url::getSeoUrl($page_id, false) ?? ((string) $page['path'] . (string) $page['resource_name']);
				$result_pages[] = CmsResourceSpecService::exportWebpageSpec($page_path);
			}

			ApiResponse::renderSuccess([
				'base_path' => $base_path,
				'count' => count($result_pages),
				'pages' => $result_pages,
			]);
		} catch (Throwable $exception) {
			ApiResponse::renderError('WEBPAGE_LIST_FAILED', $exception->getMessage(), 400);
		}
	}
}
