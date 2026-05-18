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
					BrowserEventDocumentationHelper::param('form_id', 'post', 'string', true, 'Class-backed form descriptor id.'),
					BrowserEventDocumentationHelper::param('form_instance_id', 'post', 'string', true, 'Stable placement id for this rendered form instance.'),
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
			$this->emitContextError(new ApiError('FORM_BUILD_MISMATCH', t('common.error_save')), 409);

			return;
		}

		$class_name = FormClassResolver::resolveClassName($context->formId);

		if ($class_name === null) {
			$this->emitContextError(new ApiError('FORM_NOT_FOUND', t('common.error_save')), 404);

			return;
		}

		$this->applyRuntimeGet($context);

		$missing = Request::getMissingParams($class_name::getRequiredUrlParams());

		if ($missing !== []) {
			$this->emitContextError(new ApiError('FORM_CONTEXT_MISSING_REQUIRED_PARAMS', t('common.error_save'), details: ['missing' => $missing]), 400);

			return;
		}

		if (!$context->canAccessHostContext()) {
			$this->emitContextError(new ApiError('FORM_HOST_DENIED', t('response_error.access_denied')), 403);

			return;
		}

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
			]
		);

		$files = $_FILES ?? [];
		$result = $form->process($post, $files);

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
