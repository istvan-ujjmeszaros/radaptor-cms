<?php

declare(strict_types=1);

class EventMailpitAttachment extends AbstractMailpitEvent
{
	public function run(): void
	{
		if ($this->id() === '' || $this->partId() === '') {
			ApiResponse::renderError('MAILPIT_ATTACHMENT_PARAMS_REQUIRED', 'id and part are required.', 400);

			return;
		}

		try {
			$response = $this->client()->getAttachment($this->id(), $this->partId(), $this->boolParam('thumb'));
			$content_type = $response->headerLine('content-type') ?: 'application/octet-stream';
			WebpageView::header('Content-Type: ' . $content_type);
			WebpageView::header('Cache-Control: private, max-age=60');

			$filename = trim((string) Request::_GET('filename', ''));

			if ($this->boolParam('download') && $filename !== '') {
				WebpageView::header('Content-Disposition: attachment; filename="' . addcslashes(basename($filename), '"\\') . '"');
			}

			echo $response->body;
		} catch (MailpitClientException $exception) {
			$this->renderClientError($exception);
		}
	}
}
