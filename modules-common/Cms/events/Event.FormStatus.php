<?php

declare(strict_types=1);

class EventFormStatus extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return McpCmsAuthoringAuthorization::systemDeveloper($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form.status',
			'group' => 'CMS Consistency',
			'name' => 'Show form status',
			'summary' => 'Lists registered forms and their URL placement status.',
			'description' => 'Reports default path metadata, resolved pages, and Form widget placements for registered form types.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('form', 'query', 'string', false, 'Form type to inspect. Omit to return all forms.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns registered form URL status.',
			],
			'authorization' => [
				'visibility' => 'system developers',
				'description' => 'Requires the system_developer role.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.form.status',
				'risk' => 'read',
			],
			'notes' => BrowserEventDocumentationHelper::lines('This tool is read-only and does not auto-create missing form pages.'),
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		ApiResponse::renderSuccess(CmsIntegrityInspector::inspectForms((string) Request::_GET('form', '')));
	}
}
