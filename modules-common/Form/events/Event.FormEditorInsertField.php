<?php

declare(strict_types=1);

final class EventFormEditorInsertField extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormBuilderEventHelper::authorizeContentAdmin($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form_editor.insert_field',
			'group' => 'CMS Authoring',
			'name' => 'Insert capture form field',
			'summary' => 'Inserts one palette-backed field into a DB-authored capture form draft from page edit mode.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('form_edit_insert', 'body', 'base64-json', true, 'Insert action payload.'),
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

		try {
			$payload = $this->payloadFromPost();
			$csrf_error = FormSubmitContext::validateCsrfTokenForForm(
				FormBuilderEventHelper::CSRF_INLINE_INSERT_FORM_ID,
				$payload['csrf_token'] ?? null,
			);

			if ($csrf_error !== null) {
				$this->fail($csrf_error->code, 'form.builder.error_csrf', 403);

				return;
			}

			$this->assertEditableTarget($payload);
			$definition_slug = trim((string)($payload['definition_slug'] ?? ''));
			$field_type = trim((string)($payload['field_type'] ?? ''));
			$insert_index = max(0, (int)($payload['insert_index'] ?? 0));
			$result = CmsMutationAuditService::withContext(
				'form_editor.insert_field',
				[
					'definition_slug' => $definition_slug,
					'field_type' => $field_type,
					'insert_index' => $insert_index,
				],
				static fn (): array => (new FormCaptureAuthoringService())->insertFieldIntoDraft(
					$definition_slug,
					$field_type,
					$insert_index,
				),
			);

			$this->succeed($result);
		} catch (InvalidArgumentException) {
			$this->fail('FORM_EDITOR_INSERT_INVALID', 'form.insert.error_invalid_type', 422);
		} catch (UnexpectedValueException) {
			$this->fail('FORM_EDITOR_INSERT_UNAVAILABLE', 'form.insert.error_unavailable', 403);
		} catch (Throwable $exception) {
			Kernel::logException($exception, 'Inline capture form field insert failed');
			$this->fail('FORM_EDITOR_INSERT_FAILED', 'form.insert.error_save_failed', 422);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function payloadFromPost(): array
	{
		$encoded = trim((string)Request::_POST('form_edit_insert', ''));
		$json = $encoded !== '' ? base64_decode($encoded, true) : false;

		if ($json === false) {
			throw new InvalidArgumentException('Form editor insert payload is invalid.');
		}

		try {
			$payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new InvalidArgumentException('Form editor insert payload must be valid JSON.', 0, $exception);
		}

		if (!is_array($payload)) {
			throw new InvalidArgumentException('Form editor insert payload must decode to an object.');
		}

		return $payload;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function assertEditableTarget(array $payload): void
	{
		$definition_slug = trim((string)($payload['definition_slug'] ?? ''));
		$host_page_id = (int)($payload['host_page_id'] ?? 0);
		$widget_connection_id = (int)($payload['widget_connection_id'] ?? 0);

		FormBuilderEventHelper::assertEditableCaptureTarget($definition_slug, $host_page_id, $widget_connection_id);
	}

	/**
	 * @param array<string, mixed> $result
	 */
	private function succeed(array $result): void
	{
		if (Request::wantsNonHtmlResponse()) {
			SystemMessages::flushAllMessages();
			ApiResponse::renderSuccess($result);

			return;
		}

		SystemMessages::_ok(t('form.insert.status_draft_updated'));
		Kernel::redirectToReferer();
	}

	private function fail(string $code, string $message_key, int $http_code): void
	{
		if (Request::wantsNonHtmlResponse()) {
			SystemMessages::flushAllMessages();
			ApiResponse::renderErrorObj(new ApiError($code, t($message_key)), $http_code);

			return;
		}

		SystemMessages::_error(t($message_key));
		Kernel::redirectToReferer();
	}
}
