<?php

class EventI18nAjaxSave extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return Roles::hasRole(RoleList::ROLE_I18N_TRANSLATOR)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$domain  = Request::_POST('domain', '');
		$key     = Request::_POST('key', '');
		$context = Request::_POST('context', '');
		$locale  = Request::_POST('locale', '');
		$text    = Request::_POST('text', '');
		$humanReviewedRaw = trim((string) Request::_POST('human_reviewed', '0'));
		$humanReviewed = in_array($humanReviewedRaw, ['1', 'true', 'on', 'yes'], true);
		$trimmedText = trim($text);

		if ($domain === '' || $key === '' || $locale === '') {
			http_response_code(422);
			header('Content-Type: application/json');
			echo json_encode(['error' => 'domain, key and locale are required']);

			return;
		}

		if ($trimmedText === '') {
			$result = I18nTranslationService::deleteTranslation($domain, $key, $context, $locale);
		} else {
			$result = I18nTranslationService::saveTranslation($domain, $key, $context, $locale, $text, $humanReviewed);
		}

		header('Content-Type: application/json');
		echo json_encode([
			'ok' => true,
			'action' => $result['action'],
			'text' => $trimmedText === '' ? '' : $text,
			'human_reviewed' => $trimmedText === '' ? false : $humanReviewed,
			'is_missing' => $trimmedText === '',
		]);
	}
}
