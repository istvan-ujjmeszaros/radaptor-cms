<?php

declare(strict_types=1);

final class EventFormEditorCanvas extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormBuilderEventHelper::authorizeContentAdmin($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form_editor.canvas',
			'group' => 'CMS Authoring',
			'name' => 'Form editor canvas document',
			'summary' => 'Renders the standalone editing canvas containing only the target capture form.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('definition_slug', 'query', 'string', true, 'Capture definition slug.'),
					BrowserEventDocumentationHelper::param(CmsConfig::EDITOR_IFRAME_PARAM, 'query', 'string', false, 'Editor iframe marker.'),
					BrowserEventDocumentationHelper::param(CmsConfig::EDITOR_SESSION_PARAM, 'query', 'string', false, 'Editing-session token.'),
				],
			],
			'response' => [
				'kind' => 'html',
				'content_type' => 'text/html; charset=UTF-8',
			],
			'authorization' => [
				'visibility' => 'role:content_admin',
			],
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		if (Request::getMethod() !== 'GET') {
			FormBuilderEventHelper::renderFailure('FORM_EDITOR_CANVAS_METHOD_NOT_ALLOWED', 'response_error.access_denied', 405);

			return;
		}

		try {
			WebpageView::header('Content-Type: text/html; charset=UTF-8');
			echo (new FormEditorAuthoringService())->renderEditorCanvas(
				(string)Request::_GET('definition_slug', ''),
			);
		} catch (Throwable $exception) {
			Kernel::logException($exception, 'Form editor canvas render failed');
			WebpageView::header('Content-Type: text/html; charset=UTF-8');
			http_response_code(422);
			echo '<div class="alert alert-danger m-3" role="alert">' . e(t('form.list.editor_load_failed')) . '</div>';
		}
	}
}
