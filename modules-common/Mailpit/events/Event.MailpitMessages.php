<?php

declare(strict_types=1);

class EventMailpitMessages extends AbstractMailpitEvent
{
	public function run(): void
	{
		try {
			ApiResponse::renderSuccess($this->client()->listMessages(
				max(0, (int) Request::_GET('start', 0)),
				min(100, max(1, (int) Request::_GET('limit', 50)))
			));
		} catch (MailpitClientException $exception) {
			$this->renderClientError($exception);
		}
	}
}
