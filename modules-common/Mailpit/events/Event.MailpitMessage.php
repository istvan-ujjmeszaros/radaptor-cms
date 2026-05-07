<?php

declare(strict_types=1);

class EventMailpitMessage extends AbstractMailpitEvent
{
	public function run(): void
	{
		if ($this->id() === '') {
			ApiResponse::renderError('MAILPIT_MESSAGE_ID_REQUIRED', 'id is required.', 400);

			return;
		}

		try {
			ApiResponse::renderSuccess($this->client()->getMessage($this->id()));
		} catch (MailpitClientException $exception) {
			$this->renderClientError($exception);
		}
	}
}
