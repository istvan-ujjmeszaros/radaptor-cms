<?php

declare(strict_types=1);

class EventMailpitMessagesDelete extends AbstractMailpitEvent
{
	public function run(): void
	{
		try {
			$this->client()->deleteMessages(
				$this->ids(),
				trim((string) Request::_POST('search', Request::_GET('search', ''))),
				(string) Request::_POST('tz', Request::_GET('tz', date_default_timezone_get()))
			);
			$this->redirectAfterMutation();
		} catch (MailpitClientException $exception) {
			$this->renderClientError($exception);
		}
	}
}
