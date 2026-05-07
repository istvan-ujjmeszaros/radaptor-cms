<?php

declare(strict_types=1);

abstract class AbstractMailpitEvent extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return MailpitAccessPolicy::authorize();
	}

	protected function client(): MailpitClient
	{
		return MailpitClient::fromConfig();
	}

	protected function id(): string
	{
		return trim((string) (Request::_GET('id', Request::_POST('id', ''))));
	}

	protected function partId(): string
	{
		return trim((string) (Request::_GET('part', Request::_POST('part', ''))));
	}

	/**
	 * @return list<string>
	 */
	protected function ids(): array
	{
		$ids = Request::_POST('ids', Request::_GET('ids', []));

		if (is_string($ids)) {
			$ids = preg_split('/\s*,\s*/', $ids) ?: [];
		}

		if (!is_array($ids)) {
			return [];
		}

		return array_values(array_filter(array_map(
			static fn (mixed $id): string => trim((string) $id),
			$ids
		)));
	}

	protected function boolParam(string $name, bool $default = false): bool
	{
		$value = Request::_POST($name, Request::_GET($name, $default ? 'true' : 'false'));

		return filter_var($value, FILTER_VALIDATE_BOOLEAN);
	}

	protected function renderClientError(MailpitClientException $exception): void
	{
		ApiResponse::renderError(
			'MAILPIT_REQUEST_FAILED',
			$exception->getMessage(),
			$exception->statusCode >= 400 ? $exception->statusCode : 502
		);
	}

	protected function redirectAfterMutation(): void
	{
		$redirect = trim((string) Request::_POST('redirect', Request::_GET('redirect', '/')));

		if ($redirect === '') {
			$redirect = '/';
		}

		if ((string) ($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true') {
			WebpageView::header('HX-Redirect: ' . $redirect);

			return;
		}

		Url::redirect($redirect);
	}
}
