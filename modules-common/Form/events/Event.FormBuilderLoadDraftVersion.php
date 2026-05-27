<?php

declare(strict_types=1);

final class EventFormBuilderLoadDraftVersion extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormBuilderEventHelper::authorizeContentAdmin($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form_builder.load_draft_version',
			'group' => 'CMS Authoring',
			'name' => 'Load capture form draft version',
			'summary' => 'Loads one existing DB-authored capture form version into the builder without mutating stored state.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('definition_slug', 'body', 'string', true, 'Capture definition slug.'),
					BrowserEventDocumentationHelper::param('version_id', 'body', 'int', true, 'Version id to load into the editor.'),
					BrowserEventDocumentationHelper::param('csrf_token', 'body', 'string', true, 'Session-bound builder CSRF token.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
			],
			'authorization' => [
				'visibility' => 'role:content_admin',
			],
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		if (Request::getMethod() !== 'POST') {
			FormBuilderEventHelper::renderFailure('FORM_BUILDER_METHOD_NOT_ALLOWED', 'response_error.access_denied', 405);

			return;
		}

		$csrf_error = FormBuilderEventHelper::validateCsrfFromPost();

		if ($csrf_error !== null) {
			FormBuilderEventHelper::renderCsrfError($csrf_error);

			return;
		}

		try {
			ApiResponse::renderSuccess((new FormCaptureAuthoringService())->loadDraftVersion(
				trim((string)Request::_POST('definition_slug', '')),
				(int)Request::_POST('version_id', 0),
			));
		} catch (InvalidArgumentException $exception) {
			$http_code = str_contains($exception->getMessage(), 'does not exist') ? 404 : 422;
			FormBuilderEventHelper::renderFailure('FORM_BUILDER_LOAD_DRAFT_INVALID', 'form.builder.error_load_draft', $http_code);
		} catch (Throwable $exception) {
			Kernel::logException($exception, 'Form builder load draft failed');
			FormBuilderEventHelper::renderFailure('FORM_BUILDER_LOAD_DRAFT_FAILED', 'form.builder.error_load_draft', 500);
		}
	}
}
