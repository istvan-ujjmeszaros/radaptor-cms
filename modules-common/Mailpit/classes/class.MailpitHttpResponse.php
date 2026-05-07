<?php

declare(strict_types=1);

final class MailpitHttpResponse
{
	/**
	 * @param array<string, list<string>> $headers
	 */
	public function __construct(
		public readonly int $statusCode,
		public readonly string $body,
		public readonly array $headers = [],
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function json(): array
	{
		$decoded = json_decode($this->body, true);

		return is_array($decoded) ? $decoded : [];
	}

	public function headerLine(string $name): string
	{
		$key = strtolower($name);
		$values = $this->headers[$key] ?? [];

		return implode(', ', $values);
	}
}
