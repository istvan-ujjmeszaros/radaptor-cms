<?php

declare(strict_types=1);

final class FormHookResult
{
	public const string STATUS_QUEUED = 'queued';
	public const string STATUS_DELIVERED = 'delivered';
	public const string STATUS_SUPPRESSED = 'suppressed';
	public const string STATUS_FAILED = 'failed';

	/**
	 * @param array<string, mixed> $details
	 */
	public function __construct(
		private readonly string $_status,
		private readonly array $_details = [],
		private readonly ?string $_error_code = null,
		private readonly ?string $_error_message = null,
	) {
	}

	/**
	 * @param array<string, mixed> $details
	 */
	public static function queued(array $details = []): self
	{
		return new self(self::STATUS_QUEUED, $details);
	}

	/**
	 * @param array<string, mixed> $details
	 */
	public static function delivered(array $details = []): self
	{
		return new self(self::STATUS_DELIVERED, $details);
	}

	/**
	 * @param array<string, mixed> $details
	 */
	public static function suppressed(string $error_code, string $error_message, array $details = []): self
	{
		return new self(self::STATUS_SUPPRESSED, $details, $error_code, $error_message);
	}

	/**
	 * @param array<string, mixed> $details
	 */
	public static function failed(string $error_code, string $error_message, array $details = []): self
	{
		return new self(self::STATUS_FAILED, $details, $error_code, $error_message);
	}

	public function status(): string
	{
		return $this->_status;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function details(): array
	{
		return $this->_details;
	}

	public function errorCode(): ?string
	{
		return $this->_error_code;
	}

	public function errorMessage(): ?string
	{
		return $this->_error_message;
	}
}
