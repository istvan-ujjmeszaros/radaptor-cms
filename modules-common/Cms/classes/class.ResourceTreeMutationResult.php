<?php

declare(strict_types=1);

final class ResourceTreeMutationResult
{
	private function __construct(
		public readonly bool $ok,
		public readonly mixed $data = null,
		public readonly ?ApiError $error = null,
	) {
	}

	public static function success(mixed $data = null): self
	{
		return new self(true, $data);
	}

	public static function failure(ApiError $error, mixed $data = null): self
	{
		return new self(false, $data, $error);
	}

	public static function error(string $code, string $message, array $fields = [], array $details = [], mixed $data = null): self
	{
		return self::failure(new ApiError($code, $message, $fields, $details), $data);
	}
}
