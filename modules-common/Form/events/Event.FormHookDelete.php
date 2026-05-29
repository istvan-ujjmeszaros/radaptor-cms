<?php

declare(strict_types=1);

final class EventFormHookDelete extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormHookEventHelper::authorizeConfigurator($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form_hook.delete',
			'group' => 'CMS Authoring',
			'name' => 'Delete capture form hook',
			'summary' => 'Deletes one capture form hook target configuration.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('definition_slug', 'body', 'string', true, 'Capture definition slug.'),
					BrowserEventDocumentationHelper::param('hook_id', 'body', 'int', true, 'Hook id.'),
					BrowserEventDocumentationHelper::param('csrf_token', 'body', 'string', true, 'Session-bound builder CSRF token.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
			],
			'authorization' => [
				'visibility' => 'role:content_admin|system_developer',
			],
			'side_effects' => BrowserEventDocumentationHelper::lines('Deletes a form_hook_targets row; delivery logs are retained with hook_id set null.'),
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
				'form_hook.delete',
				[
					'definition_slug' => FormHookEventHelper::definitionSlugFromRequest(),
					'hook_id' => (int)Request::_POST('hook_id', 0),
				],
				static fn (): array => (new FormHookConfigService())->deleteForForm(
					FormHookEventHelper::definitionSlugFromRequest(),
					(int)Request::_POST('hook_id', 0),
				),
			);
			ApiResponse::renderSuccess($result);
		} catch (FormHookConfigValidationException $exception) {
			FormHookEventHelper::renderException($exception);
		} catch (Throwable) {
			FormHookEventHelper::renderFailure('FORM_HOOK_DELETE_FAILED', 422);
		}
	}
}
