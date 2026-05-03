<?php

declare(strict_types=1);

class EventResourceFileUsage extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return McpCmsAuthoringAuthorization::systemDeveloper($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'resource.file_usage',
			'group' => 'CMS Consistency',
			'name' => 'Find uploaded file usage',
			'summary' => 'Finds virtual filesystem resources that reference an uploaded file.',
			'description' => 'Accepts either a media container file_id or a virtual filesystem file path and returns every resource_tree file node that links to the same uploaded file.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('file_id', 'query', 'int', false, 'Media container file id. Required when path is omitted.'),
					BrowserEventDocumentationHelper::param('path', 'query', 'string', false, 'Virtual filesystem file path. Required when file_id is omitted.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns uploaded file metadata and VFS resource references.',
			],
			'authorization' => [
				'visibility' => 'system developers',
				'description' => 'Requires the system_developer role.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.resource.file_usage',
				'risk' => 'read',
			],
			'notes' => BrowserEventDocumentationHelper::lines(
				'This checks VFS-to-media-container references, not free-text HTML links.'
			),
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		try {
			$file_id = Request::hasGet('file_id') ? (int) Request::_GET('file_id') : null;
			$path = Request::hasGet('path') ? (string) Request::_GET('path') : null;

			ApiResponse::renderSuccess(CmsUsageInspector::inspectFileUsage($file_id, $path));
		} catch (Throwable $exception) {
			ApiResponse::renderError('RESOURCE_FILE_USAGE_FAILED', $exception->getMessage(), 400);
		}
	}
}
