<?php

declare(strict_types=1);

class EventWidgetStatus extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return McpCmsAuthoringAuthorization::systemDeveloper($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'widget.status',
			'group' => 'CMS Consistency',
			'name' => 'Show widget status',
			'summary' => 'Lists registered widgets and their URL placement status.',
			'description' => 'Reports default path metadata, resolved pages, and current placements for registered widgets.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('widget', 'query', 'string', false, 'Widget type to inspect. Omit to return all widgets.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns registered widget URL status.',
			],
			'authorization' => [
				'visibility' => 'system developers',
				'description' => 'Requires the system_developer role.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.widget.status',
				'risk' => 'read',
			],
			'notes' => BrowserEventDocumentationHelper::lines('This tool is read-only and does not auto-create missing widget pages.'),
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		ApiResponse::renderSuccess(CmsIntegrityInspector::inspectWidgets((string) Request::_GET('widget', '')));
	}
}
