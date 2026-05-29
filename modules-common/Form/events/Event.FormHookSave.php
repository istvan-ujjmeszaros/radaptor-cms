<?php

declare(strict_types=1);

final class EventFormHookSave extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormHookEventHelper::authorizeConfigurator($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form_hook.save',
			'group' => 'CMS Authoring',
			'name' => 'Save capture form hook',
			'summary' => 'Creates or updates one capture form hook target configuration.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('definition_slug', 'body', 'string', true, 'Capture definition slug.'),
					BrowserEventDocumentationHelper::param('target_kind', 'body', 'string', true, 'Hook target kind.'),
					BrowserEventDocumentationHelper::param('metadata_json', 'body', 'json-object', false, 'Target metadata.'),
					BrowserEventDocumentationHelper::param('excluded_field_keys_json', 'body', 'json-list', false, 'Stable field keys to omit from hook payloads.'),
					BrowserEventDocumentationHelper::param('csrf_token', 'body', 'string', true, 'Session-bound builder CSRF token.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
			],
			'authorization' => [
				'visibility' => 'role:content_admin|system_developer; system_developer required for custom URL, secret, and non-production override.',
			],
			'side_effects' => BrowserEventDocumentationHelper::lines('Creates or updates a form_hook_targets row.'),
		];
	}

	public function run(): void
	{
		if (Request::getMethod() !== 'POST') {
			FormHookEventHelper::renderFailure('FORM_HOOK_METHOD_NOT_ALLOWED', 405);

			return;
		}

		$csrf_error = FormHookEventHelper::validateCsrfFromPost();

		if ($csrf_error !== null) {
			FormHookEventHelper::renderCsrfError($csrf_error);

			return;
		}

		try {
			$result = CmsMutationAuditService::withContext(
				'form_hook.save',
				['definition_slug' => FormHookEventHelper::definitionSlugFromRequest()],
				static fn (): array => (new FormHookConfigService())->saveForForm(
					FormHookEventHelper::definitionSlugFromRequest(),
					FormHookEventHelper::hookInputFromPost(),
				),
			);
			ApiResponse::renderSuccess($result);
		} catch (FormHookConfigValidationException $exception) {
			FormHookEventHelper::renderException($exception);
		} catch (Throwable) {
			FormHookEventHelper::renderFailure('FORM_HOOK_SAVE_FAILED', 422);
		}
	}
}
