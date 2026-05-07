<?php

declare(strict_types=1);

class EventMailpitSearch extends AbstractMailpitEvent
{
	public function run(): void
	{
		$query = trim((string) Request::_GET('query', Request::_GET('q', '')));

		if ($query === '') {
			ApiResponse::renderError('MAILPIT_SEARCH_QUERY_REQUIRED', 'query is required.', 400);

			return;
		}

		try {
			ApiResponse::renderSuccess($this->client()->searchMessages(
				$query,
				max(0, (int) Request::_GET('start', 0)),
				min(100, max(1, (int) Request::_GET('limit', 50))),
				(string) Request::_GET('tz', date_default_timezone_get())
			));
		} catch (MailpitClientException $exception) {
			$this->renderClientError($exception);
		}
	}
}
