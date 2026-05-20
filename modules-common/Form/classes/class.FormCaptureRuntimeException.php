<?php

declare(strict_types=1);

final class FormCaptureRuntimeException extends RuntimeException
{
	public function __construct(
		private readonly string $_api_code,
		private readonly string $_message_key,
		private readonly int $_http_status,
		string $debug_message = '',
		?Throwable $previous = null,
	) {
		parent::__construct($debug_message !== '' ? $debug_message : $_api_code, 0, $previous);
	}

	public static function invalidDescriptor(string $definition_slug, Throwable $previous): self
	{
		return new self(
			'FORM_CAPTURE_DESCRIPTOR_INVALID',
			'form.capture.error_unavailable',
			500,
			"Invalid capture form descriptor for {$definition_slug}.",
			$previous,
		);
	}

	public static function missingAppSecret(): self
	{
		return new self(
			'FORM_CAPTURE_SECRET_MISSING',
			'form.capture.error_unavailable',
			500,
			'APP_SECRET must be configured before capture submissions can be fingerprinted.',
		);
	}

	public function apiCode(): string
	{
		return $this->_api_code;
	}

	public function messageKey(): string
	{
		return $this->_message_key;
	}

	public function httpStatus(): int
	{
		return $this->_http_status;
	}
}
