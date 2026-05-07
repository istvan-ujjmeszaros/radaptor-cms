<?php

declare(strict_types=1);

final class MailpitClientException extends RuntimeException
{
	/**
	 * @param array<string, mixed>|null $payload
	 */
	public function __construct(
		string $message,
		public readonly int $statusCode = 0,
		public readonly ?array $payload = null,
		?Throwable $previous = null,
	) {
		parent::__construct($message, $statusCode, $previous);
	}
}
