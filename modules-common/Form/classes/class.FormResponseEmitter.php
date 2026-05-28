<?php

declare(strict_types=1);

final class FormResponseEmitter
{
	/**
	 * @param array<string, mixed> $payload
	 * @param array<string, mixed> $files
	 */
	public function emit(AbstractForm $form, FormResult $result, FormSubmitContext $context, array $payload = [], array $files = []): void
	{
		if (Request::isHtmxRequest() && !Request::isHtmxBoostedRequest()) {
			$this->emitHtmx($form, $result, $context);

			return;
		}

		if (Request::wantsNonHtmlResponse()) {
			$this->emitApi($form, $result, $context);

			return;
		}

		$this->emitClassic($form, $result, $context, $payload, $files);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param array<string, mixed> $files
	 */
	private function emitClassic(AbstractForm $form, FormResult $result, FormSubmitContext $context, array $payload, array $files): void
	{
		if ($result->isSuccess() || $result->isCancel()) {
			Url::redirect($form->getRedirectTargetForResult($result, $context), 303);
		}

		http_response_code($result->isDenied() ? 403 : 422);

		$host_redirect_target = $context->hostedInvalidRedirectTarget();

		if ($host_redirect_target !== '' && !$result->isDenied()) {
			FormSubmissionStateStore::prime($context, $result, $payload, $files);
			FormSubmissionStateStore::flash($context, $result, $payload);
			$this->emitSeeOther($host_redirect_target);

			return;
		}

		echo $this->renderFormDocument($form, $result);
	}

	private function emitHtmx(AbstractForm $form, FormResult $result, FormSubmitContext $context): void
	{
		if ($result->isSuccess() || $result->isCancel()) {
			header('HX-Redirect: ' . $form->getRedirectTargetForResult($result, $context));
			http_response_code(204);

			return;
		}

		http_response_code($result->isDenied() ? 403 : 422);
		header('HX-Retarget: #' . $form->getFormId());
		header('HX-Reswap: outerHTML');
		echo $this->renderFormFragment($form, $result);
	}

	private function emitApi(AbstractForm $form, FormResult $result, FormSubmitContext $context): void
	{
		if ($result->isSuccess() || $result->isCancel()) {
			ApiResponse::renderSuccess([
				'form' => $result->toArray(),
				'redirect' => $form->getRedirectTargetForResult($result, $context),
			]);

			return;
		}

		if ($result->isDenied()) {
			ApiResponse::renderErrorObj(
				$result->error() ?? new ApiError('FORM_DENIED', t('response_error.access_denied')),
				403,
				['form' => $result->toArray()]
			);

			return;
		}

		ApiResponse::renderErrorObj(
			new ApiError('FORM_INVALID', $result->firstError() ?? t('common.error_save')),
			422,
			['form' => $result->toArray()]
		);
	}

	private function renderFormDocument(AbstractForm $form, FormResult $result): string
	{
		return '<!doctype html><html><head><meta charset="utf-8"></head><body>'
			. $this->renderFormFragment($form, $result)
			. '</body></html>';
	}

	private function renderFormFragment(AbstractForm $form, FormResult $result): string
	{
		$renderer = new HtmlTreeRenderer(
			theme: $form->getTreeBuildContext()->getTheme(),
			lang_id: Kernel::getLocale(),
			page_id: $form->getTreeBuildContext()->getPageId(),
			is_editable: $form->getTreeBuildContext()->isEditable(),
		);
		$html = $renderer->render($form->buildTree());

		return $this->renderErrorSummary($result) . $html . $renderer->getJs();
	}

	private function emitSeeOther(string $location): void
	{
		ResourceTreeHandler::setNoCacheHeaders();
		http_response_code(303);
		WebpageView::header('Location: ' . $location);
	}

	private function renderErrorSummary(FormResult $result): string
	{
		$errors = [];

		foreach ($result->errors() as $field_errors) {
			foreach ($field_errors as $error) {
				$errors[] = $error;
			}
		}

		if ($result->isDenied()) {
			$errors[] = $result->error()?->message ?? t('response_error.access_denied');
		}

		if ($errors === []) {
			return '';
		}

		$html = '<div class="form-submit-errors" role="alert">';

		foreach (array_unique($errors) as $error) {
			$html .= '<p>' . e($error) . '</p>';
		}

		return $html . '</div>';
	}
}
