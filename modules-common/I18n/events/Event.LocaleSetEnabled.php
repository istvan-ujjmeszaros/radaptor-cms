<?php

declare(strict_types=1);

class EventLocaleSetEnabled extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'locale.set-enabled',
			'group' => 'I18n',
			'name' => 'Set locale enabled state',
			'summary' => 'Enables or disables a registered application locale.',
			'description' => 'Updates locales.is_enabled without deleting any content or translation data.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('locale', 'body', 'string', true, 'BCP 47 locale code.'),
					BrowserEventDocumentationHelper::param('enabled', 'body', 'string', true, 'Truth-y value to enable, false-y value to disable.'),
					BrowserEventDocumentationHelper::param('referer', 'query', 'string', false, 'Same-site return URL.'),
				],
			],
			'response' => [
				'kind' => 'redirect',
				'content_type' => 'text/html',
				'description' => 'Redirects back to the locale admin page.',
			],
			'authorization' => [
				'visibility' => 'role:system_developer',
				'description' => 'Requires the system developer role.',
			],
			'notes' => BrowserEventDocumentationHelper::lines(
				'APP_DEFAULT_LOCALE cannot be disabled.',
				'Disabling a locale does not delete rows that already use it.',
				'POST requests must pass same-origin Origin/Referer validation.'
			),
			'side_effects' => BrowserEventDocumentationHelper::lines(
				'Updates locales.is_enabled.',
				'Ensures the configured default locale exists and remains enabled.'
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
		$redirect_status = EventUserSetLocale::getRedirectStatusForMethod(Request::getMethod());
		$locale = LocaleService::tryCanonicalize((string) Request::_POST('locale', '')) ?? '';
		$enabled = $this->_isTruthy((string) Request::_POST('enabled', '0'));

		if ($locale === '') {
			SystemMessages::_error(t('locale_admin.message.invalid_locale'));
			// Url::redirect() is typed never and exits; this error branch cannot fall through to mutation.
			Url::redirect($referer, $redirect_status);
		}

		if (!class_exists(LocaleAdminService::class)) {
			SystemMessages::_error(t('locale_admin.message.service_unavailable'));
			// Url::redirect() is typed never and exits; this error branch cannot fall through to mutation.
			Url::redirect($referer, $redirect_status);
		}

		try {
			$locale = LocaleAdminService::setEnabled($locale, $enabled);
			SystemMessages::_notice(t(
				$enabled ? 'locale_admin.message.enabled' : 'locale_admin.message.disabled',
				['locale' => $locale]
			));
		} catch (Throwable) {
			SystemMessages::_error(t('locale_admin.message.error', ['locale' => $locale]));
		}

		Url::redirect($referer, $redirect_status);
	}

	private function _getReferer(): string
	{
		$referer = (string) Request::_GET('referer', Url::getCurrentHost());

		return class_exists(LocaleSwitchService::class)
			? LocaleSwitchService::sanitizeSameSiteReturnUrl($referer)
			: Url::sanitizeRefererUrl($referer);
	}

	private function _isTruthy(string $value): bool
	{
		return in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
	}

	private function _renderForbidden(): void
	{
		http_response_code(403);
		echo t('user.locale.forbidden');
	}

	private function _renderMethodNotAllowed(): void
	{
		http_response_code(405);
		header('Allow: POST');
		echo t('user.locale.method_not_allowed');
	}
}
