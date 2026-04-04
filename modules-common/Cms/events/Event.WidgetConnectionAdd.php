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

		if ($widget_name === '') {
			SystemMessages::_warning(t('cms.widget_connection.select_widget_type'));
			Kernel::redirectToReferer();
		}

		if (!Widget::checkWidgetExists($widget_name)) {
			Kernel::abort(__FILE__ . ': line ' . __LINE__);
		}

		$slot_name = Request::_GET('slot_name', Request::DEFAULT_ERROR);

		if (Widget::assignWidgetToWebpage(Request::_GET('pageid', Request::DEFAULT_ERROR), $slot_name, $widget_name, Request::_GET('seq'))) {
			SystemMessages::_ok(t('cms.widget_connection.added'));
		} else {
			SystemMessages::_error(t('cms.widget_connection.duplicate_not_allowed'));
		}

		Kernel::redirectToReferer();
	}
}
