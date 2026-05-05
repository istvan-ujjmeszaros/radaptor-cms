<?php

class EventUserSetLocale extends AbstractEvent implements iBrowserEventDocumentable
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
			'event_name' => 'user.set-locale',
			'group' => 'Runtime',
			'name' => 'Set current user locale',
			'summary' => 'Updates the current user interface language and redirects back.',
			'description' => 'Persists a selected available locale on the logged-in user and refreshes the current session data.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('locale', 'body', 'string', true, 'Available locale code to store on the current user.'),
					BrowserEventDocumentationHelper::param('referer', 'query', 'string', false, 'Optional sanitized return URL after saving the locale.'),
				],
			],
			'response' => [
				'kind' => 'redirect',
				'content_type' => 'text/html',
				'description' => 'Redirects back after updating the user locale.',
			],
			'authorization' => [
				'visibility' => 'logged-in users',
				'description' => 'Requires membership in the logged-in system usergroup.',
			],
			'notes' => BrowserEventDocumentationHelper::lines(
				'Locale values are limited to locales available through the runtime locale registry.',
				'Referer is sanitized before redirect.'
			),
			'side_effects' => BrowserEventDocumentationHelper::lines(
				'Writes the users.locale field for the current user.',
				'Refreshes the current user session.',
				'Queues a success/error system message.'
			),
		];
	}

	public function run(): void
	{
		$referer = $this->_getReferer();
		$locale = trim((string) Request::_POST('locale', ''));
		$available_locales = I18nRuntime::getAvailableLocaleCodes();

		if (!in_array($locale, $available_locales, true)) {
			SystemMessages::_error(t('user.locale.invalid'));
			Url::redirect($referer);
		}

		$user_id = User::getCurrentUserId();

		if ($user_id <= 0) {
			SystemMessages::_error(t('user.locale.invalid'));
			Url::redirect($referer);
		}

		DbHelper::updateHelper('users', ['locale' => $locale], $user_id);
		User::refreshUserSession();
		Kernel::setRequestLocale($locale);

		SystemMessages::_notice(t('user.locale.updated'));
		Url::redirect($referer);
	}

	private function _getReferer(): string
	{
		if (Request::_GET('referer', Request::DEFAULT_ERROR)) {
			$referer = Url::sanitizeRefererUrl((string) Request::_GET('referer', Request::DEFAULT_ERROR));

			return $referer !== '' ? $referer : Url::getCurrentHost();
		}

		return Url::getCurrentHost();
	}
}
