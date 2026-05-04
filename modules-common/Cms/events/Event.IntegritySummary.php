<?php

declare(strict_types=1);

class EventIntegritySummary extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return McpCmsAuthoringAuthorization::systemDeveloper($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'integrity.summary',
			'group' => 'CMS Consistency',
			'name' => 'Show integrity summary',
			'summary' => 'Summarizes registered layout, form, and widget integrity status.',
			'description' => 'Returns a read-only CMS integrity summary without creating or repairing content.',
			'request' => [
				'method' => 'GET',
				'params' => [],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns aggregate ok/warning/error counts for integrity checks.',
			],
			'authorization' => [
				'visibility' => 'system developers',
				'description' => 'Requires the system_developer role.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.integrity.summary',
				'risk' => 'read',
			],
			'notes' => BrowserEventDocumentationHelper::lines('This tool is read-only and never repairs content.'),
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		ApiResponse::renderSuccess(CmsIntegrityInspector::inspectSummary());
	}
}
