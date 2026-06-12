<?php

declare(strict_types=1);

final class EventFormBuilderEditorFragment extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormBuilderEventHelper::authorizeContentAdmin($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form_builder.editor_fragment',
			'group' => 'CMS Authoring',
			'name' => 'Render capture form builder editor fragment',
			'summary' => 'Renders the capture form builder chrome for insertion into the admin forms modal.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('definition_slug', 'query', 'string', true, 'Capture definition slug.'),
					BrowserEventDocumentationHelper::param('panel', 'query', 'string', false, 'Initial editor panel.'),
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
			FormBuilderEventHelper::renderFailure('FORM_BUILDER_METHOD_NOT_ALLOWED', 'response_error.access_denied', 405);

			return;
		}

		try {
			WebpageView::header('Content-Type: text/html; charset=UTF-8');
			echo (new FormEditorAuthoringService())->renderEditorFragment(
				(string)Request::_GET('definition_slug', ''),
			);
		} catch (Throwable) {
			WebpageView::header('Content-Type: text/html; charset=UTF-8');
			http_response_code(422);
			echo '<div class="alert alert-danger m-3" role="alert">' . e(t('form.list.editor_load_failed')) . '</div>';
		}
	}
}
