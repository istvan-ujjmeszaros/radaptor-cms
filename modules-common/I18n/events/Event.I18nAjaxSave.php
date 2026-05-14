<?php

class EventI18nAjaxSave extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return Roles::hasRole(RoleList::ROLE_I18N_TRANSLATOR)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'i18n_ajax.save',
			'group' => 'I18n',
			'name' => 'Save one i18n translation row',
			'summary' => 'Creates, updates, or deletes one translation row from the workbench.',
			'description' => 'Persists one translation change and returns a JSON summary including whether the row is now missing or reviewed.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('domain', 'body', 'string', true, 'Translation domain.'),
					BrowserEventDocumentationHelper::param('key', 'body', 'string', true, 'Translation key.'),
					BrowserEventDocumentationHelper::param('context', 'body', 'string', false, 'Optional message context.'),
					BrowserEventDocumentationHelper::param('locale', 'body', 'string', true, 'Target locale code.'),
					BrowserEventDocumentationHelper::param('text', 'body', 'string', true, 'New translation text; empty string deletes the translation.'),
					BrowserEventDocumentationHelper::param('human_reviewed', 'body', 'string', false, 'Truth-y flag to mark the translation as human reviewed.'),
					BrowserEventDocumentationHelper::param('allow_source_match', 'body', 'string', false, 'Truth-y flag to allow an intentional source-text match.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns a JSON status payload describing the action that happened.',
			],
			'authorization' => [
				'visibility' => 'role:i18n_translator',
				'description' => 'Requires the i18n translator role.',
			],
			'notes' => BrowserEventDocumentationHelper::lines(
				'Empty text deletes the translation instead of saving an empty string.'
			),
			'side_effects' => BrowserEventDocumentationHelper::lines(
				'Inserts, updates, or deletes one translation record.'
			),
		];
	}

	public function run(): void
	{
		$domain  = Request::_POST('domain', '');
		$key     = Request::_POST('key', '');
		$context = Request::_POST('context', '');
		$locale  = LocaleService::tryCanonicalize((string) Request::_POST('locale', '')) ?? '';
		$text    = Request::_POST('text', '');
		$humanReviewedRaw = trim((string) Request::_POST('human_reviewed', '0'));
		$humanReviewed = in_array($humanReviewedRaw, ['1', 'true', 'on', 'yes'], true);
		$allowSourceMatchRaw = trim((string) Request::_POST('allow_source_match', '0'));
		$allowSourceMatch = in_array($allowSourceMatchRaw, ['1', 'true', 'on', 'yes'], true);
		$trimmedText = trim($text);

		if ($domain === '' || $key === '' || $locale === '') {
			http_response_code(422);
			header('Content-Type: application/json');
			echo json_encode(['error' => t('common.missing_required_url_params')]);

			return;
		}

		if ($trimmedText === '') {
			$result = I18nTranslationService::deleteTranslation($domain, $key, $context, $locale);
		} else {
			$result = I18nTranslationService::saveTranslation(
				$domain,
				$key,
				$context,
				$locale,
				$text,
				$humanReviewed,
				false,
				null,
				$allowSourceMatch
			);
		}

		header('Content-Type: application/json');
		echo json_encode([
			'ok' => true,
			'action' => $result['action'],
			'text' => $trimmedText === '' ? '' : $text,
			'human_reviewed' => $trimmedText === '' ? false : $humanReviewed,
			'allow_source_match' => $trimmedText === '' ? false : (bool) ($result['allow_source_match'] ?? false),
			'is_missing' => $trimmedText === '',
		]);
	}
}
