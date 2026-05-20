<?php

declare(strict_types=1);

class EventFormSubmit extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return PolicyDecision::allow();
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form.submit',
			'group' => 'Runtime',
			'name' => 'Submit form',
			'summary' => 'Dedicated form submission chokepoint that resolves, binds, validates, and processes a form before response emission.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('form_id', 'post', 'string', true, 'Class-backed system form id or published capture definition_slug.'),
					BrowserEventDocumentationHelper::param('form_instance_id', 'post', 'string', true, 'Stable placement id for this rendered form instance.'),
					BrowserEventDocumentationHelper::param('csrf_token', 'post', 'string', true, 'Session-bound form CSRF token.'),
				],
			],
			'response' => [
				'kind' => 'redirect|html-fragment|api-json',
				'content_type' => 'text/html or application/json',
			],
			'authorization' => [
				'visibility' => 'public route with form ACL and host-context checks',
			],
		];
	}

	public function run(): void
	{
		if (Request::getMethod() !== 'POST') {
			$this->emitContextError(new ApiError('FORM_METHOD_NOT_ALLOWED', t('response_error.access_denied')), 405);

			return;
		}

		$post = Request::getPOST();
		$context = FormSubmitContext::fromPost($post);

		if ($context === null) {
			$this->emitContextError(new ApiError('FORM_CONTEXT_INVALID', t('common.error_save')), 400);

			return;
		}

		if (!$context->isCurrentBuild()) {
			$this->emitContextError(new ApiError('FORM_BUILD_MISMATCH', t('response_error.internal.refresh_page')), 409);

			return;
		}

		try {
			$resolution = FormDefinitionResolver::resolve($context->formId);
		} catch (FormCaptureRuntimeException $exception) {
			$this->emitContextError(new ApiError($exception->apiCode(), t($exception->messageKey())), $exception->httpStatus());

			return;
		}

		if ($resolution === null) {
			$message_key = FormCaptureDescriptorSchemaValidator::isCaptureSlug($context->formId)
				? 'form.capture.error_unavailable'
				: 'common.error_save';
			$this->emitContextError(new ApiError('FORM_NOT_FOUND', t($message_key)), 404);

			return;
		}

		$this->applyRuntimeGet($context);

		$missing = $resolution->isSystem()
			? Request::getMissingParams($resolution->className()::getRequiredUrlParams())
			: [];

		if ($missing !== []) {
			$this->emitContextError(new ApiError('FORM_CONTEXT_MISSING_REQUIRED_PARAMS', t('common.error_save'), details: ['missing' => $missing]), 400);

			return;
		}

		if (!$context->canAccessHostContext()) {
			$this->emitContextError(new ApiError('FORM_HOST_DENIED', t('response_error.access_denied')), 403);

			return;
		}

		try {
			$form = Form::factory(
				$context->formId,
				$context->formInstanceId,
				FormSubmitTreeBuildContext::fromSubmitContext($context),
				$context->returnTarget,
				[
					'item_id' => $context->itemId,
					'host_page_id' => $context->hostPageId,
					'widget_connection_id' => $context->widgetConnectionId,
					'return_target' => $context->returnTarget,
					'form_definition_resolution' => $resolution,
				]
			);
		} catch (FormCaptureRuntimeException $exception) {
			$this->emitContextError(new ApiError($exception->apiCode(), t($exception->messageKey())), $exception->httpStatus());

			return;
		}

		$files = $_FILES ?? [];
		$csrf_error = $context->validateCsrfToken($post);

		if ($csrf_error !== null) {
			if (Request::wantsNonHtmlResponse()) {
				SystemMessages::flushAllMessages();
			}

			(new FormResponseEmitter())->emit($form, FormResult::denied($csrf_error), $context, $post, $files);

			return;
		}

		try {
			$result = $form->process($post, $files);
		} catch (FormCaptureRuntimeException $exception) {
			$this->emitContextError(new ApiError($exception->apiCode(), t($exception->messageKey())), $exception->httpStatus());

			return;
		}

		if (Request::wantsNonHtmlResponse()) {
			SystemMessages::flushAllMessages();
		}

		(new FormResponseEmitter())->emit($form, $result, $context, $post, $files);
	}

	private function emitContextError(ApiError $error, int $httpCode): void
	{
		if (Request::wantsNonHtmlResponse()) {
			SystemMessages::flushAllMessages();
			ApiResponse::renderErrorObj($error, $httpCode);

			return;
		}

		http_response_code($httpCode);
		echo '<div class="form-submit-errors" role="alert"><p>' . e($error->message) . '</p></div>';
	}

	private function applyRuntimeGet(FormSubmitContext $context): void
	{
		$ctx = RequestContextHolder::current();
		$get = $context->toRuntimeGet();
		$ctx->GET = $get;
		$_GET = $get;
	}
}
