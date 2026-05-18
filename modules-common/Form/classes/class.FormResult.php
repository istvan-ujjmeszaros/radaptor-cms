<?php

declare(strict_types=1);

final class FormResult
{
	public const string OUTCOME_SUCCESS = 'success';
	public const string OUTCOME_CANCEL = 'cancel';
	public const string OUTCOME_INVALID = 'invalid';
	public const string OUTCOME_DENIED = 'denied';

	/**
	 * @param array<string, list<string>> $errors
	 * @param array<string, mixed> $data
	 */
	private function __construct(
		private readonly string $outcome,
		private readonly array $errors = [],
		private readonly array $data = [],
		private readonly ?ApiError $error = null,
	) {
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function success(array $data = []): self
	{
		return new self(self::OUTCOME_SUCCESS, data: $data);
	}

	public static function cancel(): self
	{
		return new self(self::OUTCOME_CANCEL);
	}

	/**
	 * @param array<string, list<string>> $errors
	 * @param array<string, mixed> $data
	 */
	public static function invalid(array $errors, array $data = []): self
	{
		return new self(self::OUTCOME_INVALID, $errors, $data);
	}

	public static function denied(?ApiError $error = null): self
	{
		return new self(self::OUTCOME_DENIED, error: $error);
	}

	public function outcome(): string
	{
		return $this->outcome;
	}

	public function isSuccess(): bool
	{
		return $this->outcome === self::OUTCOME_SUCCESS;
	}

	public function isCancel(): bool
	{
		return $this->outcome === self::OUTCOME_CANCEL;
	}

	public function isInvalid(): bool
	{
		return $this->outcome === self::OUTCOME_INVALID;
	}

	public function isDenied(): bool
	{
		return $this->outcome === self::OUTCOME_DENIED;
	}

	/**
	 * @return array<string, list<string>>
	 */
	public function errors(): array
	{
		return $this->errors;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function data(): array
	{
		return $this->data;
	}

	public function error(): ?ApiError
	{
		return $this->error;
	}

	public function firstError(): ?string
	{
		foreach ($this->errors as $field_errors) {
			foreach ($field_errors as $error) {
				return $error;
			}
		}

		return $this->error?->message;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		$result = [
			'outcome' => $this->outcome,
		];

		if ($this->errors !== []) {
			$result['errors'] = $this->errors;
		}

		if ($this->data !== []) {
			$result['data'] = $this->data;
		}

		if ($this->error !== null) {
			$result['error'] = $this->error->toArray();
		}

		return $result;
	}
}
