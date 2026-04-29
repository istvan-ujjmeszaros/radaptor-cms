<?php

declare(strict_types=1);

class EventWebpageInfo extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return McpCmsAuthoringAuthorization::webpagePath(
			(string) Request::_GET('path', ''),
			ResourceAcl::_ACL_VIEW,
			$policyContext
		);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'webpage.info',
			'group' => 'CMS Authoring',
			'name' => 'Show webpage details',
			'summary' => 'Returns one webpage spec.',
			'description' => 'Returns path, layout, attributes, ACL, catcher flag, and widget slots for a webpage.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('path', 'query', 'string', true, 'Webpage path.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns the webpage spec.',
			],
			'authorization' => [
				'visibility' => 'resource ACL',
				'description' => 'Requires view permission on the webpage.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.webpage.info',
				'risk' => 'read',
			],
			'notes' => [],
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		$path = trim((string) Request::_GET('path', ''));

		if ($path === '') {
			ApiResponse::renderError('MISSING_PATH', 'path is required.', 400);

			return;
		}

		try {
			ApiResponse::renderSuccess(CmsResourceSpecService::exportWebpageSpec($path));
		} catch (Throwable $exception) {
			ApiResponse::renderError('WEBPAGE_INFO_FAILED', $exception->getMessage(), 400);
		}
	}
}
