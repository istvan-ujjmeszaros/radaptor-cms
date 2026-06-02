<?php

class EventWidgetConnectionAdd extends AbstractEvent implements iBrowserEventDocumentable
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
			'event_name' => 'widget_connection.add',
			'group' => 'Editing',
			'name' => 'Add widget connection',
			'summary' => 'Assigns a widget to a webpage slot and redirects back to the editor.',
			'description' => 'Used by the page editor when a user inserts a widget into a slot on a webpage.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('widget_name', 'body', 'string', true, 'Widget class name to assign.'),
					BrowserEventDocumentationHelper::param('pageid', 'query', 'int', true, 'Target webpage id.'),
					BrowserEventDocumentationHelper::param('slot_name', 'query', 'string', true, 'Target slot name on the webpage layout.'),
					BrowserEventDocumentationHelper::param('seq', 'query', 'int', false, 'Optional insertion position within the slot.'),
				],
			],
			'response' => [
				'kind' => 'redirect',
				'content_type' => 'text/html',
				'description' => 'Redirects back to the referer after adding the widget or reporting an error.',
			],
			'authorization' => [
				'visibility' => 'logged-in users',
				'description' => 'Requires membership in the logged-in system usergroup.',
			],
			'notes' => BrowserEventDocumentationHelper::lines(
				'The widget_name comes from POST; placement information is taken from query parameters.'
			),
			'side_effects' => BrowserEventDocumentationHelper::lines(
				'Creates a widget_connections row when the assignment succeeds.',
				'Queues a system message for success or duplicate/error cases.'
			),
		];
	}

	public function run(): void
	{
		$widget_name = Request::_POST('widget_name', '');
		$page_id = (int)Request::_GET('pageid', Request::DEFAULT_ERROR);
		$slot_name = (string)Request::_GET('slot_name', Request::DEFAULT_ERROR);
		$seq_value = Request::_GET('seq', null);
		$seq = $seq_value === null || $seq_value === '' ? null : (int)$seq_value;

		if ($widget_name === '') {
			$this->fail('WIDGET_CONNECTION_ADD_EMPTY', 'cms.widget_connection.select_widget_type', 422);

			return;
		}

		if (!Widget::checkWidgetExists($widget_name)) {
			Kernel::abort(__FILE__ . ': line ' . __LINE__);
		}

		$connection_id = Widget::assignWidgetToWebpage($page_id, $slot_name, $widget_name, $seq);

		if ($connection_id !== false) {
			(new EditModeMutationResponder())->succeed(
				'cms.widget_connection.added',
				$page_id,
				[EditModeMutationCommand::replaceSlot($slot_name, 'edit-widget-' . (int)$connection_id)],
				[
					'connection_id' => (int)$connection_id,
				],
			);
		} else {
			$this->fail('WIDGET_CONNECTION_DUPLICATE', 'cms.widget_connection.duplicate_not_allowed', 422);
		}
	}

	private function fail(string $code, string $message_key, int $http_code): void
	{
		if ((Request::isHtmxRequest() && !Request::isHtmxBoostedRequest()) || Request::wantsNonHtmlResponse()) {
			(new EditModeMutationResponder())->fail($code, $message_key, $http_code);

			return;
		}

		if ($code === 'WIDGET_CONNECTION_ADD_EMPTY') {
			SystemMessages::_warning(t($message_key));
		} else {
			SystemMessages::_error(t($message_key));
		}

		Kernel::redirectToReferer();
	}
}
