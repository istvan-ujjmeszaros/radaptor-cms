<?php

class EventWidgetConnectionSwap extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->inGroup(Usergroups::SYSTEMUSERGROUP_LOGGEDIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'widget_connection.swap',
			'group' => 'Editing',
			'name' => 'Swap widget positions',
			'summary' => 'Swaps two widget connection positions and redirects back.',
			'description' => 'Used by editor controls that reorder widgets within a page slot.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('item_id', 'query', 'int', true, 'Primary widget connection id.'),
					BrowserEventDocumentationHelper::param('swap_id', 'query', 'int', true, 'Second widget connection id to swap with.'),
				],
			],
			'response' => [
				'kind' => 'redirect',
				'content_type' => 'text/html',
				'description' => 'Redirects back after attempting the reorder operation.',
			],
			'authorization' => [
				'visibility' => 'logged-in users',
				'description' => 'Requires membership in the logged-in system usergroup.',
			],
			'notes' => [],
			'side_effects' => BrowserEventDocumentationHelper::lines(
				'Swaps sequence values in the widget_connections table.',
				'Queues a success system message when the swap succeeds.'
			),
		];
	}

	public function run(): void
	{
		$table = 'widget_connections';

		if (DbHelper::swapHelper($table, Request::_GET('item_id', Request::DEFAULT_ERROR), Request::_GET('swap_id', Request::DEFAULT_ERROR))) {
			SystemMessages::_ok(t('cms.widget_connection.moved'));
		}

		Kernel::redirectToReferer();
	}
}
