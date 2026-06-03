<?php

class EventWidgetConnectionRemove extends AbstractEvent implements iBrowserEventDocumentable
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
			'event_name' => 'widget_connection.remove',
			'group' => 'Editing',
			'name' => 'Remove widget connection',
			'summary' => 'Deletes one widget assignment from a webpage and redirects back.',
			'description' => 'Used by the page editor when a widget should be removed from the current layout.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('item_id', 'query', 'int', true, 'Widget connection id to remove.'),
				],
			],
			'response' => [
				'kind' => 'redirect',
				'content_type' => 'text/html',
				'description' => 'Redirects back to the referer after the delete attempt.',
			],
			'authorization' => [
				'visibility' => 'logged-in users',
				'description' => 'Requires membership in the logged-in system usergroup.',
			],
			'notes' => [],
			'side_effects' => BrowserEventDocumentationHelper::lines(
				'Deletes one widget connection row when the target exists.',
				'Queues a success system message when the remove succeeds.'
			),
		];
	}

	public function run(): void
	{
		$item_id = (int)Request::_GET('item_id', Request::DEFAULT_ERROR);
		$connection_data = Widget::getConnectionData($item_id);
		$page_id = (int)($connection_data['page_id'] ?? 0);
		$slot_name = (string)($connection_data['slot_name'] ?? '');

		if (Widget::removeWidgetFromWebpage($item_id)) {
			try {
				(new EditModeMutationResponder())->succeed(
					'cms.widget_connection.removed',
					$page_id,
					[EditModeMutationCommand::replaceSlot($slot_name)],
				);
			} catch (InvalidArgumentException|RuntimeException) {
				$this->fail('WIDGET_CONNECTION_REMOVE_RENDER_FAILED', 'cms.widget_connection.remove_error', 500);
			}

			return;
		}

		$this->fail('WIDGET_CONNECTION_REMOVE_FAILED', 'cms.widget_connection.remove_error', 422);
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
