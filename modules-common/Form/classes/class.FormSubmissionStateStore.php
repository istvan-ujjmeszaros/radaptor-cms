<?php

declare(strict_types=1);

final class FormSubmissionStateStore
{
	/** @var array<string, array{result: FormResult, payload: array<string, mixed>, files: array<string, mixed>}> */
	private static array $states = [];

	/**
	 * @param array<string, mixed> $payload
	 * @param array<string, mixed> $files
	 */
	public static function prime(FormSubmitContext $context, FormResult $result, array $payload, array $files = []): void
	{
		self::$states[self::key($context->formId, $context->formInstanceId)] = [
			'result' => $result,
			'payload' => $payload,
			'files' => $files,
		];
	}

	/**
	 * @return array{result: FormResult, payload: array<string, mixed>, files: array<string, mixed>}|null
	 */
	public static function get(AbstractForm $form): ?array
	{
		return self::$states[self::key($form->getFormType(), $form->getFormInstanceId())] ?? null;
	}

	public static function clear(): void
	{
		self::$states = [];
	}

	private static function key(string $formId, string $formInstanceId): string
	{
		return $formId . "\0" . $formInstanceId;
	}
}
