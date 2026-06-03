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
		$before = [
			Widget::getConnectionData($item_id),
			Widget::getConnectionData($swap_id),
		];

		if (!is_array($before[0]) || !is_array($before[1])) {
			$this->fail('WIDGET_CONNECTION_SWAP_FAILED', 'cms.widget_connection.move_error', 422);

			return;
		}

		if (DbHelper::swapHelper($table, $item_id, $swap_id)) {
			foreach ([$item_id, $swap_id] as $connection_id) {
				CmsRenderVersion::touchWidgetConnection($connection_id);
			}

			$commands = [];
			$page_id = 0;

			foreach ($before as $connection_data) {
				if (!is_array($connection_data)) {
					continue;
				}

				$page_id = $page_id > 0 ? $page_id : (int)($connection_data['page_id'] ?? 0);
				$slot_name = (string)($connection_data['slot_name'] ?? '');

				if ($slot_name !== '') {
					$commands[$slot_name] = EditModeMutationCommand::replaceSlot($slot_name);
				}
			}

			try {
				(new EditModeMutationResponder())->succeed(
					'cms.widget_connection.moved',
					$page_id,
					array_values($commands),
				);
			} catch (InvalidArgumentException|RuntimeException) {
				$this->fail('WIDGET_CONNECTION_SWAP_RENDER_FAILED', 'cms.widget_connection.move_error', 500);
			}

			return;
		}

		$this->fail('WIDGET_CONNECTION_SWAP_FAILED', 'cms.widget_connection.move_error', 422);
	}

	private function fail(string $code, string $message_key, int $http_code): void
	{
		if ((Request::isHtmxRequest() && !Request::isHtmxBoostedRequest()) || Request::wantsNonHtmlResponse()) {
			(new EditModeMutationResponder())->fail($code, $message_key, $http_code);

			return;
		}

		SystemMessages::_error(t($message_key));
		Kernel::redirectToReferer();
	}
}
