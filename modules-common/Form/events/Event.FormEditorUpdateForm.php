<?php

declare(strict_types=1);

final class EventFormEditorUpdateForm extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormBuilderEventHelper::authorizeContentAdmin($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form_editor.update_form',
			'group' => 'CMS Authoring',
			'name' => 'Update capture form properties',
			'summary' => 'Updates the capture form draft title, description, or submit label from the unified form editor.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('form_edit_update_form', 'body', 'base64-json', true, 'Form property payload.'),
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
		$responder = new EditModeMutationResponder();

		if (Request::getMethod() !== 'POST') {
			$responder->fail('FORM_EDITOR_METHOD_NOT_ALLOWED', 'response_error.access_denied', 405);

			return;
		}

		try {
			$payload = $this->payloadFromPost();
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
			FormBuilderEventHelper::assertEditableCaptureTarget($definition_slug, $host_page_id, $widget_connection_id);
			$submitted = is_array($payload['properties'] ?? null) ? $payload['properties'] : [];

			$result = CmsMutationAuditService::withContext(
				'form_editor.update_form',
				[
					'definition_slug' => $definition_slug,
					'properties' => array_keys($submitted),
				],
				static fn (): array => (new FormCaptureAuthoringService())->updateFormPropertiesInDraft(
					$definition_slug,
					$submitted,
				),
			);

			$responder->succeed(
				'form.insert.status_draft_updated',
				$host_page_id,
				[
					EditModeMutationCommand::replaceForm($widget_connection_id),
					EditModeMutationCommand::replaceWidgetToolbar($widget_connection_id),
				],
				$result,
			);
		} catch (InvalidArgumentException) {
			$responder->fail('FORM_EDITOR_UPDATE_FORM_INVALID', 'form.insert.error_invalid_type', 422);
		} catch (Throwable $exception) {
			Kernel::logException($exception, 'Capture form property update failed');
			$responder->fail('FORM_EDITOR_UPDATE_FORM_FAILED', 'form.insert.error_save_failed', 422);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function payloadFromPost(): array
	{
		$encoded = trim((string)Request::_POST('form_edit_update_form', ''));
		$json = $encoded !== '' ? base64_decode($encoded, true) : false;

		if ($json === false) {
			throw new InvalidArgumentException('Form editor update payload is invalid.');
		}

		try {
			$payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new InvalidArgumentException('Form editor update payload must be valid JSON.', 0, $exception);
		}

		if (!is_array($payload)) {
			throw new InvalidArgumentException('Form editor update payload must decode to an object.');
		}

		return $payload;
	}
}
