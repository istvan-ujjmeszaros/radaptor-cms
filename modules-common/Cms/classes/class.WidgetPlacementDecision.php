<?php

declare(strict_types=1);

final class WidgetPlacementDecision
{
	private function __construct(
		private readonly bool $allowed,
		private readonly string $code = '',
		private readonly string $messageKey = '',
	) {
	}

	public static function allow(): self
	{
		return new self(true);
	}

	public static function deny(string $code, string $message_key): self
	{
		return new self(false, $code, $message_key);
	}

	public function isAllowed(): bool
	{
		return $this->allowed;
	}

	public function code(): string
	{
		return $this->code;
	}

	public function messageKey(): string
	{
		return $this->messageKey;
	}
}
