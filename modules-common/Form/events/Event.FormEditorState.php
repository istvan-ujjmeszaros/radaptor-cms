<?php

declare(strict_types=1);

final class EventFormEditorState extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormBuilderEventHelper::authorizeContentAdmin($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form_editor.state',
			'group' => 'CMS Authoring',
			'name' => 'Capture form editor panel state',
			'summary' => 'Returns the recent versions, page usage, and session undo reach for the unified form editor panel.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('definition_slug', 'query', 'string', true, 'Capture definition slug.'),
					BrowserEventDocumentationHelper::param(CmsConfig::EDITOR_SESSION_PARAM, 'query', 'string', false, 'Editing-session token for undo/redo reach.'),
				],
			],
			'response' => [
				'kind' => 'api-json',
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
		if (Request::getMethod() !== 'GET') {
			FormBuilderEventHelper::renderFailure('FORM_EDITOR_STATE_METHOD_NOT_ALLOWED', 'response_error.access_denied', 405);

			return;
		}

		try {
			ApiResponse::renderSuccess((new FormCaptureAuthoringService())->editorStateForDefinition(
				trim((string)Request::_GET('definition_slug', '')),
				CmsConfig::editorSessionToken(),
			));
		} catch (Throwable) {
			FormBuilderEventHelper::renderFailure('FORM_EDITOR_STATE_FAILED', 'form.list.editor_load_failed', 422);
		}
	}
}
