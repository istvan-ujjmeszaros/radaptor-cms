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
		if (Widget::removeWidgetFromWebpage(Request::_GET('item_id', Request::DEFAULT_ERROR))) {
			SystemMessages::_ok(t('cms.widget_connection.removed'));
		}

		Kernel::redirectToReferer();
	}
}
