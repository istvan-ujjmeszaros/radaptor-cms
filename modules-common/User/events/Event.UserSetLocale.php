<?php

class EventUserSetLocale extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return PolicyDecision::allow();
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'user.set-locale',
			'group' => 'Runtime',
			'name' => 'Set current user locale',
			'summary' => 'Updates the current request language and redirects back.',
			'description' => 'Persists a selected enabled locale on the logged-in user or anonymous session, then redirects to the same dynamic page or the selected locale home resource for fixed-locale content.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('locale', 'body', 'string', true, 'Enabled BCP 47 locale code.'),
					BrowserEventDocumentationHelper::param('referer', 'query', 'string', false, 'Optional same-site return URL after saving the locale.'),
				],
			],
			'response' => [
				'kind' => 'redirect',
				'content_type' => 'text/html',
				'description' => 'Redirects back after updating the user locale.',
			],
			'authorization' => [
				'visibility' => 'public',
				'description' => 'Public locale switcher endpoint. Logged-in users also get users.locale updated.',
			],
			'notes' => BrowserEventDocumentationHelper::lines(
				'Locale values are limited to enabled locales.',
				'Referer is sanitized to a same-site URL before redirect.',
				'POST requests must pass same-origin Origin/Referer validation.'
			),
			'side_effects' => BrowserEventDocumentationHelper::lines(
				'Writes users.locale for logged-in users.',
				'Stores anonymous locale in session and cookie.',
				'Queues a success/error system message.'
			),
		];
	}

	public function run(): void
	{
		if (Request::getMethod() !== 'POST') {
			$this->_renderMethodNotAllowed();

			return;
		}

		if (class_exists(LocaleSwitchService::class) && !LocaleSwitchService::isSameOriginPostRequest()) {
			$this->_renderForbidden();

			return;
		}

		$referer = $this->_getReferer();
		$redirect_status = self::getRedirectStatusForMethod(Request::getMethod());
		$locale = LocaleService::tryCanonicalize((string) Request::_POST('locale', '')) ?? '';

		if ($locale === '' || !LocaleService::isEnabled($locale)) {
			SystemMessages::_error(t('user.locale.invalid'));
			// Url::redirect() is typed never and exits; this error branch cannot fall through to mutation.
			Url::redirect($referer, $redirect_status);
		}

		$user_id = User::getCurrentUserId();

		if ($user_id > 0) {
			DbHelper::updateHelper('users', ['locale' => $locale], $user_id);
			User::refreshUserSession();
		} elseif (class_exists(LocaleSwitchService::class)) {
			LocaleSwitchService::persistAnonymousLocale($locale);
		}

		Kernel::setRequestLocale($locale);

		$redirect_url = $referer;

		if (class_exists(LocaleSwitchService::class)) {
			$decision = LocaleSwitchService::resolveRedirectDecisionForLocale($referer, $locale);
			$redirect_url = $decision['url'];

			if (in_array($decision['reason'], [
				LocaleSwitchService::REDIRECT_REASON_MISSING_LOCALE_HOME,
				LocaleSwitchService::REDIRECT_REASON_HOME_URL_UNAVAILABLE,
			], true)) {
				SystemMessages::_warning(t('user.locale.home_missing'));
			}
		}

		SystemMessages::_notice(t('user.locale.updated'));
		Url::redirect($redirect_url, $redirect_status);
	}

	public static function getRedirectStatusForMethod(string $method): int
	{
		return strtoupper($method) === 'POST' ? 303 : 302;
	}

	private function _getReferer(): string
	{
		if (Request::_GET('referer', '') !== '') {
			$referer = class_exists(LocaleSwitchService::class)
				? LocaleSwitchService::sanitizeSameSiteReturnUrl((string) Request::_GET('referer', ''))
				: Url::sanitizeRefererUrl((string) Request::_GET('referer', ''));

			return $referer !== '' ? $referer : Url::getCurrentHost();
		}

		return Url::getCurrentHost();
	}

	private function _renderForbidden(): void
	{
		http_response_code(403);
	}

	private function _renderMethodNotAllowed(): void
	{
		WebpageView::header('Allow: POST');
		http_response_code(405);
	}
}
