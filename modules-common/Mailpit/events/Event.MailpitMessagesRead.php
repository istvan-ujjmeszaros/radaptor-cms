<?php

declare(strict_types=1);

class EventMailpitMessagesRead extends AbstractMailpitEvent
{
	public function run(): void
	{
		if (Request::getMethod() !== 'POST') {
			ApiResponse::renderError('METHOD_NOT_ALLOWED', 'This endpoint accepts POST requests only.', 405);

			return;
		}

		$ids = $this->ids();
		$search = trim((string) Request::_POST('search', ''));
		$all = filter_var(Request::_POST('all', 'false'), FILTER_VALIDATE_BOOLEAN);

		if ($ids === [] && $search === '' && !$all) {
			ApiResponse::renderError('MAILPIT_READ_TARGET_REQUIRED', 'ids or search is required.', 400);

			return;
		}

		try {
			$this->client()->setRead(
				$ids,
				filter_var(Request::_POST('read', 'true'), FILTER_VALIDATE_BOOLEAN),
				$search,
				(string) Request::_POST('tz', date_default_timezone_get()),
				$all
			);
			$this->redirectAfterMutation();
		} catch (MailpitClientException $exception) {
			$this->renderClientError($exception);
		}
	}
}
