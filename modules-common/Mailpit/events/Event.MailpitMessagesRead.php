<?php

declare(strict_types=1);

class EventMailpitMessagesRead extends AbstractMailpitEvent
{
	public function run(): void
	{
		try {
			$this->client()->setRead(
				$this->ids(),
				$this->boolParam('read', true),
				trim((string) Request::_POST('search', Request::_GET('search', ''))),
				(string) Request::_POST('tz', Request::_GET('tz', date_default_timezone_get()))
			);
			$this->redirectAfterMutation();
		} catch (MailpitClientException $exception) {
			$this->renderClientError($exception);
		}
	}
}
