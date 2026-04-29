<?php

declare(strict_types=1);

class EventWidgetSync extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return McpCmsAuthoringAuthorization::webpagePath(
			(string) Request::_POST('path', ''),
			ResourceAcl::_ACL_EDIT,
			$policyContext
		);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'widget.sync',
			'group' => 'CMS Authoring',
			'name' => 'Sync slot widgets',
			'summary' => 'Reconciles one webpage slot.',
			'description' => 'Replaces one slot with the provided ordered widget spec list.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('path', 'body', 'string', true, 'Webpage path.'),
					BrowserEventDocumentationHelper::param('slot', 'body', 'string', true, 'Slot name.'),
					BrowserEventDocumentationHelper::param('widgets', 'body', 'json-array', true, 'Ordered widget specs.'),
					BrowserEventDocumentationHelper::param('dry_run', 'body', 'bool', false, 'Validate without mutating.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns created connection snapshots.',
			],
			'authorization' => [
				'visibility' => 'resource ACL',
				'description' => 'Requires edit permission on the webpage.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.widget.sync',
				'risk' => 'write',
			],
			'notes' => [],
			'side_effects' => BrowserEventDocumentationHelper::lines('Deletes and recreates widget connections for the selected slot.'),
		];
	}

	public function run(): void
	{
		$path = trim((string) Request::_POST('path', ''));
		$slot = trim((string) Request::_POST('slot', ''));
		$widgets = Request::_POST('widgets', null);
		$dry_run = filter_var(Request::_POST('dry_run', false), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;

		if ($path === '') {
			ApiResponse::renderError('MISSING_PATH', 'path is required.', 400);

			return;
		}

		if ($slot === '') {
			ApiResponse::renderError('MISSING_SLOT', 'slot is required.', 400);

			return;
		}

		if ($widgets === null) {
			ApiResponse::renderError('MISSING_WIDGETS', 'widgets is required.', 400);

			return;
		}

		if (!is_array($widgets)) {
			ApiResponse::renderError('INVALID_WIDGET_SPEC', 'widgets must be an array.', 400);

			return;
		}

		try {
			ApiResponse::renderSuccess([
				'dry_run' => $dry_run,
				'connections' => $dry_run ? [] : CmsResourceSpecService::syncWidgetSlot($path, $slot, array_values($widgets)),
			]);
		} catch (Throwable $exception) {
			ApiResponse::renderError('WIDGET_SYNC_FAILED', $exception->getMessage(), 400);
		}
	}
}
