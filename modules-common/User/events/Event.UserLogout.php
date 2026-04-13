<?php

class EventUserLogout extends AbstractEvent implements iBrowserEventDocumentable
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
			'event_name' => 'user.logout',
			'group' => 'Runtime',
			'name' => 'Log out current user',
			'summary' => 'Ends the current user session and redirects back.',
			'description' => 'Logs out the current user, queues a logout notice, and redirects to the sanitized referer or site root.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('referer', 'query', 'string', false, 'Optional return URL after logout.'),
				],
			],
			'response' => [
				'kind' => 'redirect',
				'content_type' => 'text/html',
				'description' => 'Redirects after the session is cleared.',
			],
			'authorization' => [
				'visibility' => 'logged-in users',
				'description' => 'Requires membership in the logged-in system usergroup.',
			],
			'notes' => BrowserEventDocumentationHelper::lines(
				'Referer is sanitized to strip dangerous or redundant parameters before redirect.'
			),
			'side_effects' => BrowserEventDocumentationHelper::lines(
				'Clears the current user session.',
				'Queues a logout success message.'
			),
		];
	}

	public function run(): void
	{
		if (Request::_GET('referer', Request::DEFAULT_ERROR)) {
			$referer = Url::sanitizeRefererUrl((string) Request::_GET('referer', Request::DEFAULT_ERROR));
		} else {
			$referer = Url::getCurrentHost();
		}

		User::logout();

		if (User::getCurrentUserId() < 0) {
			SystemMessages::_notice(t('user.logout.success'));
		}

		Url::redirect($referer);
	}
}
