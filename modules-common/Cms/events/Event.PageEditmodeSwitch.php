<?php

class EventPageEditmodeSwitch extends AbstractEvent implements iBrowserEventDocumentable
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
			'event_name' => 'page_editmode.switch',
			'group' => 'Editing',
			'name' => 'Toggle page edit mode',
			'summary' => 'Enables or disables edit mode for the current logged-in user and redirects back.',
			'description' => 'Persists the edit-mode flag in user config and redirects to the referer or site root after changing the flag.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('set', 'query', 'string', true, 'Expected values are 0 or 1.'),
					BrowserEventDocumentationHelper::param('referer', 'query', 'string', false, 'Optional explicit return URL after the toggle.'),
				],
			],
			'response' => [
				'kind' => 'redirect',
				'content_type' => 'text/html',
				'description' => 'Redirects back after updating the edit-mode flag.',
			],
			'authorization' => [
				'visibility' => 'logged-in users',
				'description' => 'Requires membership in the logged-in system usergroup.',
			],
			'notes' => BrowserEventDocumentationHelper::lines(
				'The referer parameter is optional; without it the current host is used.'
			),
			'side_effects' => BrowserEventDocumentationHelper::lines(
				'Writes the CmsConfig::EDITMODE user setting.',
				'Queues a success/error system message.'
			),
		];
	}

	public function run(): void
	{
		if (Request::_GET('referer', Request::DEFAULT_ERROR)) {
			$referer = Request::_GET('referer', Request::DEFAULT_ERROR);
		} else {
			$referer = Url::getCurrentHost();
		}

		$value = Request::_GET('set');

		if (in_array($value, [
			'0',
			'1',
		])) {
			$result = User::setConfig(CmsConfig::EDITMODE, $value);

			if (is_null($result)) {
				SystemMessages::_error(t('cms.edit_mode.save_error'));
			} elseif ($value) {
				SystemMessages::_notice(t('cms.edit_mode.enabled'));
			} else {
				SystemMessages::_notice(t('cms.edit_mode.disabled'));
			}
		}

		Url::redirect($referer);
	}
}
