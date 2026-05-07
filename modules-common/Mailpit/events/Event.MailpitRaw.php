<?php

declare(strict_types=1);

class EventMailpitRaw extends AbstractMailpitEvent
{
	public function run(): void
	{
		if ($this->id() === '') {
			ApiResponse::renderError('MAILPIT_MESSAGE_ID_REQUIRED', 'id is required.', 400);

			return;
		}

		try {
			$raw = $this->client()->getRaw($this->id());
			WebpageView::header('Content-Type: text/plain; charset=UTF-8');
			echo $raw;
		} catch (MailpitClientException $exception) {
			$this->renderClientError($exception);
		}
	}
}
