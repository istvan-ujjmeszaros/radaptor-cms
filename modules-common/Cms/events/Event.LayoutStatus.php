<?php

declare(strict_types=1);

class EventLayoutStatus extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return McpCmsAuthoringAuthorization::systemDeveloper($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'layout.status',
			'group' => 'CMS Consistency',
			'name' => 'Show layout status',
			'summary' => 'Lists registered layout files and render contract status.',
			'description' => 'Checks layout templates for required renderer calls and reports explicit contract skips.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('layout', 'query', 'string', false, 'Layout id to inspect. Omit to return all layout files.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns layout contract status for registered layout files.',
			],
			'authorization' => [
				'visibility' => 'system developers',
				'description' => 'Requires the system_developer role.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.layout.status',
				'risk' => 'read',
			],
			'notes' => BrowserEventDocumentationHelper::lines('This tool is read-only and never repairs layout files.'),
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		ApiResponse::renderSuccess(CmsIntegrityInspector::inspectLayouts((string) Request::_GET('layout', '')));
	}
}
