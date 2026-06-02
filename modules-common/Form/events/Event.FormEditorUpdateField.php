<?php

declare(strict_types=1);

final class EventFormEditorUpdateField extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormBuilderEventHelper::authorizeContentAdmin($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form_editor.update_field',
			'group' => 'CMS Authoring',
			'name' => 'Update capture form field',
			'summary' => 'Updates one DB-authored capture form field property set from page edit mode.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('definition_slug', 'body', 'string', true, 'Capture definition slug.'),
					BrowserEventDocumentationHelper::param('field_key', 'body', 'string', true, 'Current field key or name.'),
					BrowserEventDocumentationHelper::param('field_index', 'body', 'int', false, 'Current field index fallback.'),
					BrowserEventDocumentationHelper::param('field_label', 'body', 'string', true, 'Field label text.'),
					BrowserEventDocumentationHelper::param('field_name', 'body', 'string', true, 'Field internal name.'),
					BrowserEventDocumentationHelper::param('field_key_new', 'body', 'string', true, 'Field payload key.'),
					BrowserEventDocumentationHelper::param('field_required', 'body', 'bool', false, 'Required validator toggle.'),
					BrowserEventDocumentationHelper::param('field_options', 'body', 'string', false, 'Newline-separated value=label options for option fields.'),
					BrowserEventDocumentationHelper::param('csrf_token', 'body', 'string', true, 'Session-bound inline field editor CSRF token.'),
				],
			],
			'response' => [
				'kind' => 'redirect|api-json',
				'content_type' => 'text/html or application/json',
			],
			'authorization' => [
				'visibility' => 'role:content_admin',
			],
			'side_effects' => BrowserEventDocumentationHelper::lines('Creates or replaces the active draft version without publishing it.'),
		];
	}

	public function run(): void
	{
		if (Request::getMethod() !== 'POST') {
			$this->fail('FORM_EDITOR_METHOD_NOT_ALLOWED', 'response_error.access_denied', 405);

			return;
		}

		$csrf_error = FormSubmitContext::validateCsrfTokenForForm(
			FormBuilderEventHelper::CSRF_INLINE_FIELD_PROPERTIES_FORM_ID,
			Request::_POST(FormSubmitContext::FIELD_CSRF_TOKEN, null),
		);

		if ($csrf_error !== null) {
			$this->fail($csrf_error->code, 'form.builder.error_csrf', 403);

			return;
		}

		$definition_slug = trim((string)Request::_POST('definition_slug', ''));
		$field_uid = trim((string)Request::_POST('field_uid', ''));
		$field_key = trim((string)Request::_POST('field_key', ''));
		$field_index = (int)Request::_POST('field_index', -1);
		$host_page_id = (int)Request::_POST('host_page_id', 0);
		$widget_connection_id = (int)Request::_POST('widget_connection_id', 0);

		try {
			FormBuilderEventHelper::assertEditableCaptureTarget($definition_slug, $host_page_id, $widget_connection_id);
			$submitted = [
				'field_label' => (string)Request::_POST('field_label', ''),
				'field_name' => (string)Request::_POST('field_name', ''),
				'field_key_new' => (string)Request::_POST('field_key_new', ''),
				'field_required' => FormBuilderEventHelper::boolPost('field_required'),
				'field_options' => (string)Request::_POST('field_options', ''),
			];
			$result = CmsMutationAuditService::withContext(
				'form_editor.update_field',
				[
					'definition_slug' => $definition_slug,
					'field_uid' => $field_uid,
					'field_key' => $field_key,
					'field_index' => $field_index,
				],
				static fn (): array => (new FormCaptureAuthoringService())->updateFieldPropertiesInDraft(
					$definition_slug,
					$field_key,
					$field_index,
					$submitted,
					$field_uid,
				),
			);
			$field_uid = FormCaptureFieldIdentity::normalizeUid((string)($result['field_uid'] ?? $field_uid));
			$this->responder()->succeed(
				'form.field_edit.status_draft_updated',
				$host_page_id,
				[EditModeMutationCommand::replaceFormField($widget_connection_id, $field_uid)],
				$result,
			);
		} catch (InvalidArgumentException) {
			$this->fail('FORM_EDITOR_FIELD_INVALID', 'form.field_edit.error_invalid', 422);
		} catch (UnexpectedValueException) {
			$this->fail('FORM_EDITOR_FIELD_UNAVAILABLE', 'form.field_edit.error_unavailable', 403);
		} catch (Throwable $exception) {
			Kernel::logException($exception, 'Inline capture form field update failed');
			$this->fail('FORM_EDITOR_FIELD_FAILED', 'form.field_edit.error_save_failed', 422);
		}
	}

	private function fail(string $code, string $message_key, int $http_code): void
	{
		$this->responder()->fail($code, $message_key, $http_code);
	}

	private function responder(): EditModeMutationResponder
	{
		return new EditModeMutationResponder();
	}
}
