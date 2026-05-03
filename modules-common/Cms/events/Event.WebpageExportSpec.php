<?php

declare(strict_types=1);

class EventWebpageExportSpec extends AbstractEvent implements iBrowserEventDocumentable
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
			'event_name' => 'webpage.export_spec',
			'group' => 'CMS Authoring',
			'name' => 'Export webpage spec',
			'summary' => 'Exports one webpage as an idempotent authoring spec.',
			'description' => 'Returns the same structured webpage spec shape accepted by webpage.create/update so agents can verify migrated CMS state.',
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
				'tool_name' => 'radaptor.webpage.export_spec',
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
			ApiResponse::renderError('WEBPAGE_EXPORT_SPEC_FAILED', $exception->getMessage(), 400);
		}
	}
}
