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

		$item_id = (int) Request::_GET('item_id', Request::DEFAULT_ERROR);
		$swap_id = (int) Request::_GET('swap_id', Request::DEFAULT_ERROR);

		if (DbHelper::swapHelper($table, $item_id, $swap_id)) {
			foreach ([$item_id, $swap_id] as $connection_id) {
				CmsRenderVersion::touchWidgetConnection($connection_id);
			}

			SystemMessages::_ok(t('cms.widget_connection.moved'));
		}

		Kernel::redirectToReferer();
	}
}
