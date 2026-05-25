<?php

declare(strict_types=1);

final class EventFormBuilderPublish extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormBuilderEventHelper::authorizeContentAdmin($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form_builder.publish',
			'group' => 'CMS Authoring',
			'name' => 'Publish capture form draft',
			'summary' => 'Promotes a DB-authored capture form draft version to the live published version.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('definition_slug', 'body', 'string', true, 'Capture definition slug.'),
					BrowserEventDocumentationHelper::param('version_id', 'body', 'int', false, 'Draft version id to publish. Defaults to active draft.'),
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
			'side_effects' => BrowserEventDocumentationHelper::lines('Updates form_definition_versions and moves form_definitions.published_version_id.'),
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
				'form_builder.publish',
				['definition_slug' => $definition_slug, 'version_id' => $version_id > 0 ? $version_id : null],
				static fn (): array => (new FormCaptureAuthoringService())->publishDraft(
					$definition_slug,
					$version_id > 0 ? $version_id : null,
				),
			);
			ApiResponse::renderSuccess($result);
		} catch (Throwable) {
			FormBuilderEventHelper::renderFailure('FORM_BUILDER_PUBLISH_FAILED', 'form.builder.error_publish', 422);
		}
	}
}
