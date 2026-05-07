<?php

declare(strict_types=1);

class EventMailpitMessagesDelete extends AbstractMailpitEvent
{
	public function run(): void
	{
		if (Request::getMethod() !== 'POST') {
			ApiResponse::renderError('METHOD_NOT_ALLOWED', 'This endpoint accepts POST requests only.', 405);

			return;
		}

		$ids = $this->ids();
		$search = trim((string) Request::_POST('search', ''));
		$delete_all = $this->boolParam('delete_all');

		if ($ids === [] && $search === '' && !$delete_all) {
			ApiResponse::renderError('MAILPIT_DELETE_TARGET_REQUIRED', 'ids or search is required.', 400);

			return;
		}

		try {
			$this->client()->deleteMessages(
				$ids,
				$search,
				(string) Request::_POST('tz', date_default_timezone_get()),
				$delete_all
			);
			$this->redirectAfterMutation();
		} catch (MailpitClientException $exception) {
			$this->renderClientError($exception);
		}
	}
}
