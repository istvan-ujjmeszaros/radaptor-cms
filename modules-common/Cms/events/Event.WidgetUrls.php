<?php

declare(strict_types=1);

class EventWidgetUrls extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return McpCmsAuthoringAuthorization::loggedIn($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'widget.urls',
			'group' => 'CMS Authoring',
			'name' => 'Find widget URLs',
			'summary' => 'Finds pages where a widget is assigned.',
			'description' => 'Returns widget placements visible to the current user.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('widget', 'query', 'string', true, 'Widget class name.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns visible widget placements.',
			],
			'authorization' => [
				'visibility' => 'logged-in users and resource ACL',
				'description' => 'Requires a logged-in user and filters placements by view permission on each page.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.widget.urls',
				'risk' => 'read',
			],
			'notes' => [],
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		$widget_name = trim((string) Request::_GET('widget', ''));

		if ($widget_name === '') {
			ApiResponse::renderError('MISSING_WIDGET', 'widget is required.', 400);

			return;
		}

		$placements = [];

		foreach (CmsAuthoringQueryHelper::getWidgetPlacements($widget_name) as $placement) {
			if (ResourceAcl::canAccessResource((int) $placement['page_id'], ResourceAcl::_ACL_VIEW)) {
				$placements[] = $placement;
			}
		}

		ApiResponse::renderSuccess([
			'widget' => $widget_name,
			'pages' => $placements,
		]);
	}
}
