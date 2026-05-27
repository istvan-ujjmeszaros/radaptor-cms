<?php

declare(strict_types=1);

final class EventFormBuilderUpdateDraftNote extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormBuilderEventHelper::authorizeContentAdmin($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form_builder.update_draft_note',
			'group' => 'CMS Authoring',
			'name' => 'Update capture form draft note',
			'summary' => 'Updates the optional maintainer note attached to a capture form version.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('definition_slug', 'body', 'string', true, 'Capture definition slug.'),
					BrowserEventDocumentationHelper::param('version_id', 'body', 'int', true, 'Version id to annotate.'),
					BrowserEventDocumentationHelper::param('author_note', 'body', 'string', false, 'Optional maintainer note, truncated to 1000 characters.'),
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
			'side_effects' => BrowserEventDocumentationHelper::lines('Updates form_definition_versions.author_note for the selected version row.'),
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

		$definition_slug = trim((string)Request::_POST('definition_slug', ''));
		$version_id = (int)Request::_POST('version_id', 0);

		try {
			$result = CmsMutationAuditService::withContext(
				'form_builder.update_draft_note',
				['definition_slug' => $definition_slug, 'version_id' => $version_id],
				static fn (): array => (new FormCaptureAuthoringService())->updateDraftNote(
					$definition_slug,
					$version_id,
					(string)Request::_POST('author_note', ''),
				),
			);
			ApiResponse::renderSuccess($result);
		} catch (InvalidArgumentException $exception) {
			$http_code = str_contains($exception->getMessage(), 'does not exist') ? 404 : 422;
			FormBuilderEventHelper::renderFailure('FORM_BUILDER_DRAFT_NOTE_INVALID', 'form.builder.error_draft_note', $http_code);
		} catch (Throwable $exception) {
			Kernel::logException($exception, 'Form builder draft note update failed');
			FormBuilderEventHelper::renderFailure('FORM_BUILDER_DRAFT_NOTE_FAILED', 'form.builder.error_draft_note', 500);
		}
	}
}
