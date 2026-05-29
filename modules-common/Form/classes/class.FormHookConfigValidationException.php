<?php

declare(strict_types=1);

final class FormHookConfigValidationException extends InvalidArgumentException
{
	/**
	 * @param array<string, mixed> $fields
	 * @param array<string, mixed> $details
	 */
	public function __construct(
		private readonly string $_api_code,
		private readonly string $_message_key = 'common.error_save',
		private readonly int $_http_status = 422,
		private readonly array $_fields = [],
		private readonly array $_details = [],
	) {
		parent::__construct($_api_code);
	}

	public static function developerRoleRequired(string $field): self
	{
		return new self(
			'FORM_HOOK_DEVELOPER_ROLE_REQUIRED',
			'response_error.access_denied',
			403,
			[$field => ['system_developer']],
		);
	}

	public function httpStatus(): int
	{
		return $this->_http_status;
	}

	public function toApiError(): ApiError
	{
		return new ApiError(
			$this->_api_code,
			t($this->_message_key),
			$this->_fields,
			$this->_details,
		);
	}
}
