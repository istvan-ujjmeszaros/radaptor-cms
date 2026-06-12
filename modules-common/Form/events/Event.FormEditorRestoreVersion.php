<?php

declare(strict_types=1);

final class EventFormEditorRestoreVersion extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormBuilderEventHelper::authorizeContentAdmin($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form_editor.restore_version',
			'group' => 'CMS Authoring',
			'name' => 'Restore capture form version',
			'summary' => 'Replaces the working capture form draft with a stored version descriptor.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('form_edit_restore', 'body', 'base64-json', true, 'Restore payload with the target version id.'),
				],
			],
			'response' => [
				'kind' => 'redirect|api-json',
				'content_type' => 'text/html or application/json',
			],
			'authorization' => [
				'visibility' => 'role:content_admin',
			],
			'side_effects' => BrowserEventDocumentationHelper::lines(
				'Replaces the active draft version with the stored version descriptor.',
				'Records the restore in the editing session history, so it is undoable.',
			),
		];
	}

	public function run(): void
	{
		$responder = new EditModeMutationResponder();

		if (Request::getMethod() !== 'POST') {
			$responder->fail('FORM_EDITOR_RESTORE_METHOD_NOT_ALLOWED', 'response_error.access_denied', 405);

			return;
		}

		try {
			$payload = FormBuilderEventHelper::decodeBase64JsonPayload((string)Request::_POST('form_edit_restore', ''));
			$csrf_error = FormSubmitContext::validateCsrfTokenForForm(
				FormBuilderEventHelper::CSRF_INLINE_INSERT_FORM_ID,
				$payload['csrf_token'] ?? null,
			);

			if ($csrf_error !== null) {
				$responder->fail($csrf_error->code, 'form.builder.error_csrf', 403);

				return;
			}

			$definition_slug = trim((string)($payload['definition_slug'] ?? ''));
			$host_page_id = (int)($payload['host_page_id'] ?? 0);
			$widget_connection_id = (int)($payload['widget_connection_id'] ?? 0);
			$version_id = (int)($payload['version_id'] ?? 0);
			FormBuilderEventHelper::assertEditableCaptureTarget($definition_slug, $host_page_id, $widget_connection_id);

			$service = new FormCaptureAuthoringService();
			$result = CmsMutationAuditService::withContext(
				'form_editor.restore_version',
				['definition_slug' => $definition_slug, 'version_id' => $version_id],
				static fn (): array => $service->restoreVersionToDraft($definition_slug, $version_id, CmsConfig::editorSessionToken()),
			);

			$responder->succeed(
				'form.editor.status_restored',
				$host_page_id,
				[
					EditModeMutationCommand::replaceForm($widget_connection_id),
					EditModeMutationCommand::replaceWidgetToolbar($widget_connection_id),
				],
				$result,
			);
		} catch (InvalidArgumentException) {
			$responder->fail('FORM_EDITOR_RESTORE_INVALID', 'form.editor.error_restore_failed', 422);
		} catch (Throwable $exception) {
			Kernel::logException($exception, 'Capture form version restore failed');
			$responder->fail('FORM_EDITOR_RESTORE_FAILED', 'form.editor.error_restore_failed', 422);
		}
	}
}
