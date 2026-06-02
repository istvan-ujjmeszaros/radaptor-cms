<?php

declare(strict_types=1);

final class EventFormEditorPublish extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormBuilderEventHelper::authorizeContentAdmin($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form_editor.publish',
			'group' => 'CMS Authoring',
			'name' => 'Publish capture form draft from edit mode',
			'summary' => 'Promotes the active DB-authored capture form draft from page edit mode.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('definition_slug', 'body', 'string', true, 'Capture definition slug.'),
					BrowserEventDocumentationHelper::param('host_page_id', 'body', 'int', true, 'Webpage id that hosts the edited capture form widget.'),
					BrowserEventDocumentationHelper::param('widget_connection_id', 'body', 'int', true, 'Capture form widget connection id.'),
					BrowserEventDocumentationHelper::param('csrf_token', 'body', 'string', true, 'Session-bound inline form command CSRF token.'),
				],
			],
			'response' => [
				'kind' => 'redirect|api-json',
				'content_type' => 'text/html or application/json',
			],
			'authorization' => [
				'visibility' => 'role:content_admin',
			],
			'side_effects' => BrowserEventDocumentationHelper::lines('Publishes the active draft version and refreshes the rendered capture form in edit mode.'),
		];
	}

	public function run(): void
	{
		if (Request::getMethod() !== 'POST') {
			$this->fail('FORM_EDITOR_METHOD_NOT_ALLOWED', 'response_error.access_denied', 405);

			return;
		}

		$csrf_error = FormSubmitContext::validateCsrfTokenForForm(
			FormBuilderEventHelper::CSRF_INLINE_FORM_COMMAND_FORM_ID,
			Request::_POST(FormSubmitContext::FIELD_CSRF_TOKEN, null),
		);

		if ($csrf_error !== null) {
			$this->fail($csrf_error->code, 'form.builder.error_csrf', 403);

			return;
		}

		$definition_slug = trim((string)Request::_POST('definition_slug', ''));
		$host_page_id = (int)Request::_POST('host_page_id', 0);
		$widget_connection_id = (int)Request::_POST('widget_connection_id', 0);

		try {
			FormBuilderEventHelper::assertEditableCaptureTarget($definition_slug, $host_page_id, $widget_connection_id);
			$result = CmsMutationAuditService::withContext(
				'form_editor.publish',
				[
					'definition_slug' => $definition_slug,
					'host_page_id' => $host_page_id,
					'widget_connection_id' => $widget_connection_id,
				],
				static fn (): array => (new FormCaptureAuthoringService())->publishDraft($definition_slug),
			);
			$this->responder()->succeed(
				'form.builder.status.published',
				$host_page_id,
				[EditModeMutationCommand::replaceForm($widget_connection_id)],
				$result,
			);
		} catch (InvalidArgumentException) {
			$this->fail('FORM_EDITOR_PUBLISH_INVALID', 'form.builder.error_publish', 422);
		} catch (UnexpectedValueException) {
			$this->fail('FORM_EDITOR_PUBLISH_UNAVAILABLE', 'form.builder.error_publish', 403);
		} catch (Throwable $exception) {
			Kernel::logException($exception, 'Inline capture form publish failed');
			$this->fail('FORM_EDITOR_PUBLISH_FAILED', 'form.builder.error_publish', 422);
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
