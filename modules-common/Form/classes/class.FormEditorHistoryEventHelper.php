<?php

declare(strict_types=1);

/**
 * Shared runner for the form editor undo/redo events: both decode the same payload,
 * apply one edit-history step through FormCaptureAuthoringService, and respond with
 * the same EditModeMutationResponder command set.
 */
final class FormEditorHistoryEventHelper
{
	public static function run(string $direction): void
	{
		$responder = new EditModeMutationResponder();

		if (Request::getMethod() !== 'POST') {
			$responder->fail('FORM_EDITOR_HISTORY_METHOD_NOT_ALLOWED', 'response_error.access_denied', 405);

			return;
		}

		try {
			$payload = self::payloadFromPost();
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

			$service = new FormCaptureAuthoringService();
			$result = CmsMutationAuditService::withContext(
				'form_editor.' . $direction,
				['definition_slug' => $definition_slug],
				static fn (): array => $direction === 'undo'
					? $service->undoEdit($definition_slug)
					: $service->redoEdit($definition_slug),
			);

			$responder->succeed(
				$direction === 'undo' ? 'form.editor.status_undone' : 'form.editor.status_redone',
				$host_page_id,
				[
					EditModeMutationCommand::replaceForm($widget_connection_id),
					EditModeMutationCommand::replaceWidgetToolbar($widget_connection_id),
				],
				$result,
			);
		} catch (InvalidArgumentException) {
			$responder->fail('FORM_EDITOR_HISTORY_UNAVAILABLE', 'form.editor.error_history_unavailable', 422);
		} catch (Throwable $exception) {
			Kernel::logException($exception, 'Capture form edit history step failed');
			$responder->fail('FORM_EDITOR_HISTORY_FAILED', 'form.insert.error_save_failed', 422);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function payloadFromPost(): array
	{
		$encoded = trim((string)Request::_POST('form_edit_history', ''));
		$json = $encoded !== '' ? base64_decode($encoded, true) : false;

		if ($json === false) {
			throw new InvalidArgumentException('Form editor history payload is invalid.');
		}

		try {
			$payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new InvalidArgumentException('Form editor history payload must be valid JSON.', 0, $exception);
		}

		if (!is_array($payload)) {
			throw new InvalidArgumentException('Form editor history payload must decode to an object.');
		}

		return $payload;
	}
}
