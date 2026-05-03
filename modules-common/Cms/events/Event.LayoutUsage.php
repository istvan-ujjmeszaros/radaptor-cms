<?php

declare(strict_types=1);

class EventLayoutUsage extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return McpCmsAuthoringAuthorization::systemDeveloper($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'layout.usage',
			'group' => 'CMS Consistency',
			'name' => 'Find layout usage',
			'summary' => 'Finds webpages that use a layout.',
			'description' => 'Returns pages using the requested layout, or layout usage counts when no layout is supplied.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('layout', 'query', 'string', false, 'Layout id to inspect. Omit to return counts for all layouts.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns layout usage count and page references.',
			],
			'authorization' => [
				'visibility' => 'system developers',
				'description' => 'Requires the system_developer role.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.layout.usage',
				'risk' => 'read',
			],
			'notes' => [],
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		ApiResponse::renderSuccess(
			CmsUsageInspector::inspectLayoutUsage((string) Request::_GET('layout', ''))
		);
	}
}
