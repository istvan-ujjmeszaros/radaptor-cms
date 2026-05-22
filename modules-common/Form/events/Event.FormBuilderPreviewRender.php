<?php

declare(strict_types=1);

final class EventFormBuilderPreviewRender extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormBuilderEventHelper::authorizeContentAdmin($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form_builder.preview_render',
			'group' => 'CMS Authoring',
			'name' => 'Render capture form builder preview',
			'summary' => 'Validates a capture descriptor and renders a non-persistent preview fragment for the builder iframe.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('definition_slug', 'body', 'string', true, 'Capture definition slug.'),
					BrowserEventDocumentationHelper::param('descriptor_json', 'body', 'json-object', true, 'Capture descriptor JSON.'),
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
			ApiResponse::renderSuccess((new FormCaptureAuthoringService())->renderPreview(
				(string)Request::_POST('definition_slug', 'capture-preview'),
				FormBuilderEventHelper::descriptorFromPost(),
			));
		} catch (Throwable) {
			FormBuilderEventHelper::renderFailure('FORM_BUILDER_PREVIEW_FAILED', 'form.builder.error_preview', 422);
		}
	}
}
