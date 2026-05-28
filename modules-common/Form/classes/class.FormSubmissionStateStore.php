<?php

declare(strict_types=1);

final class FormSubmissionStateStore
{
	public const string SESSION_KEY_FLASH_STATES = 'formSubmissionFlashStates';

	/** @var array<string, array{context: array<string, mixed>, result: FormResult, payload: array<string, mixed>, files: array<string, mixed>}> */
	private static array $states = [];

	/**
	 * @param array<string, mixed> $payload
	 * @param array<string, mixed> $files
	 */
	public static function prime(FormSubmitContext $context, FormResult $result, array $payload, array $files = []): void
	{
		self::$states[self::key($context->formId, $context->formInstanceId)] = [
			'context' => self::contextToArray($context),
			'result' => $result,
			'payload' => $payload,
			'files' => $files,
		];
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public static function flash(FormSubmitContext $context, FormResult $result, array $payload): void
	{
		if (!self::hasSessionStorage()) {
			return;
		}

		$now = time();
		$bag = self::normalizeFlashBag(Request::_SESSION(self::SESSION_KEY_FLASH_STATES, []), $now);
		$session_key = self::sessionKey($context->formId, $context->formInstanceId);
		$context_data = self::contextToArray($context);

		$bag[$session_key] = $context_data + [
			'payload' => self::normalizePayload($payload),
			'errors' => self::normalizeErrors($result->errors()),
			'outcome' => $result->outcome(),
			'error' => $result->error()?->toArray(),
			'data' => self::normalizePayload($result->data()),
			'created_at' => $now,
			'expires_at' => $now + FormSubmitContext::CSRF_TOKEN_TTL_SECONDS,
			'last_seen_at' => $now,
		];

		Request::saveSessionData([self::SESSION_KEY_FLASH_STATES], self::limitFlashBag($bag));
	}

	/**
	 * @param FormSubmitContext|null $context
	 * @return array{result: FormResult, payload: array<string, mixed>, files: array<string, mixed>}|null
	 */
	public static function get(AbstractForm $form, ?FormSubmitContext $context = null): ?array
	{
		$key = self::key($form->getFormType(), $form->getFormInstanceId());

		if ($context !== null) {
			$flash = self::consumeFlash($context);

			if ($flash !== null) {
				unset(self::$states[$key]);

				return [
					'result' => self::resultFromFlash($flash),
					'payload' => self::normalizePayload($flash['payload'] ?? []),
					'files' => [],
				];
			}
		}

		$state = self::$states[$key] ?? null;

		if (is_array($state) && ($context === null || self::matchesContext($state['context'], $context))) {
			return [
				'result' => $state['result'],
				'payload' => $state['payload'],
				'files' => $state['files'],
			];
		}

		return null;
	}

	public static function clear(): void
	{
		self::$states = [];

		if (self::hasSessionStorage()) {
			Request::saveSessionData([self::SESSION_KEY_FLASH_STATES], []);
		}
	}

	private static function key(string $formId, string $formInstanceId): string
	{
		return $formId . "\0" . $formInstanceId;
	}

	private static function sessionKey(string $formId, string $formInstanceId): string
	{
		return hash('sha256', $formId . "\0" . $formInstanceId);
	}

	private static function hasSessionStorage(): bool
	{
		return class_exists(SessionContextHolder::class) && SessionContextHolder::hasStorage();
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function contextToArray(FormSubmitContext $context): array
	{
		return [
			'form_id' => $context->formId,
			'form_instance_id' => $context->formInstanceId,
			'item_id' => $context->itemId,
			'host_page_id' => $context->hostPageId,
			'widget_connection_id' => $context->widgetConnectionId,
			'build_id' => $context->buildId,
			'form_definition_version_id' => $context->formDefinitionVersionId,
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function consumeFlash(FormSubmitContext $context): ?array
	{
		if (!self::hasSessionStorage()) {
			return null;
		}

		$now = time();
		$bag = self::normalizeFlashBag(Request::_SESSION(self::SESSION_KEY_FLASH_STATES, []), $now);
		$session_key = self::sessionKey($context->formId, $context->formInstanceId);
		$entry = $bag[$session_key] ?? null;

		if (!is_array($entry)) {
			Request::saveSessionData([self::SESSION_KEY_FLASH_STATES], $bag);

			return null;
		}

		if (!self::matchesContext($entry, $context)) {
			Request::saveSessionData([self::SESSION_KEY_FLASH_STATES], $bag);

			return null;
		}

		unset($bag[$session_key]);
		Request::saveSessionData([self::SESSION_KEY_FLASH_STATES], $bag);

		return $entry;
	}

	/**
	 * @param array<string, mixed> $entry
	 */
	private static function matchesContext(array $entry, FormSubmitContext $context): bool
	{
		if (!hash_equals((string)($entry['form_id'] ?? ''), $context->formId)) {
			return false;
		}

		if (!hash_equals((string)($entry['form_instance_id'] ?? ''), $context->formInstanceId)) {
			return false;
		}

		if (self::positiveIntOrNull($entry['item_id'] ?? null) !== $context->itemId) {
			return false;
		}

		if (self::positiveIntOrNull($entry['host_page_id'] ?? null) !== $context->hostPageId) {
			return false;
		}

		if (self::positiveIntOrNull($entry['widget_connection_id'] ?? null) !== $context->widgetConnectionId) {
			return false;
		}

		if (self::positiveIntOrNull($entry['form_definition_version_id'] ?? null) !== $context->formDefinitionVersionId) {
			return false;
		}

		$entry_build_id = is_scalar($entry['build_id'] ?? null) ? (string)$entry['build_id'] : '';

		return $entry_build_id === ''
			|| $context->buildId === ''
			|| hash_equals($entry_build_id, $context->buildId);
	}

	/**
	 * @param mixed $bag
	 * @return array<string, array<string, mixed>>
	 */
	private static function normalizeFlashBag(mixed $bag, int $now): array
	{
		if (!is_array($bag)) {
			return [];
		}

		$normalized = [];

		foreach ($bag as $session_key => $entry) {
			if (!is_string($session_key) || !is_array($entry)) {
				continue;
			}

			$expires_at = (int)($entry['expires_at'] ?? 0);

			if ($expires_at <= $now) {
				continue;
			}

			$form_id = is_scalar($entry['form_id'] ?? null) ? trim((string)$entry['form_id']) : '';
			$form_instance_id = is_scalar($entry['form_instance_id'] ?? null) ? trim((string)$entry['form_instance_id']) : '';

			if ($form_id === '' || $form_instance_id === '') {
				continue;
			}

			$normalized[$session_key] = [
				'form_id' => $form_id,
				'form_instance_id' => $form_instance_id,
				'item_id' => self::positiveIntOrNull($entry['item_id'] ?? null),
				'host_page_id' => self::positiveIntOrNull($entry['host_page_id'] ?? null),
				'widget_connection_id' => self::positiveIntOrNull($entry['widget_connection_id'] ?? null),
				'build_id' => is_scalar($entry['build_id'] ?? null) ? (string)$entry['build_id'] : '',
				'form_definition_version_id' => self::positiveIntOrNull($entry['form_definition_version_id'] ?? null),
				'payload' => self::normalizePayload($entry['payload'] ?? []),
				'errors' => self::normalizeErrors($entry['errors'] ?? []),
				'outcome' => is_scalar($entry['outcome'] ?? null) ? (string)$entry['outcome'] : FormResult::OUTCOME_INVALID,
				'error' => is_array($entry['error'] ?? null) ? $entry['error'] : null,
				'data' => self::normalizePayload($entry['data'] ?? []),
				'created_at' => (int)($entry['created_at'] ?? $expires_at),
				'expires_at' => $expires_at,
				'last_seen_at' => (int)($entry['last_seen_at'] ?? $expires_at),
			];
		}

		return self::limitFlashBag($normalized);
	}

	/**
	 * @param array<string, mixed> $entry
	 */
	private static function resultFromFlash(array $entry): FormResult
	{
		$outcome = (string)($entry['outcome'] ?? FormResult::OUTCOME_INVALID);
		$errors = self::normalizeErrors($entry['errors'] ?? []);
		$data = self::normalizePayload($entry['data'] ?? []);

		return match ($outcome) {
			FormResult::OUTCOME_SUCCESS => FormResult::success($data),
			FormResult::OUTCOME_CANCEL => FormResult::cancel(),
			FormResult::OUTCOME_DENIED => FormResult::denied(self::apiErrorFromFlash($entry['error'] ?? null)),
			default => FormResult::invalid($errors, $data),
		};
	}

	private static function apiErrorFromFlash(mixed $error): ?ApiError
	{
		if (!is_array($error)) {
			return null;
		}

		$code = is_scalar($error['code'] ?? null) ? trim((string)$error['code']) : '';
		$message = is_scalar($error['message'] ?? null) ? (string)$error['message'] : '';

		if ($code === '' || $message === '') {
			return null;
		}

		return new ApiError(
			$code,
			$message,
			is_array($error['fields'] ?? null) ? $error['fields'] : [],
			is_array($error['details'] ?? null) ? $error['details'] : [],
		);
	}

	/**
	 * @param mixed $value
	 * @return array<string, mixed>
	 */
	private static function normalizePayload(mixed $value): array
	{
		if (!is_array($value)) {
			return [];
		}

		$normalized = [];

		foreach ($value as $key => $item) {
			if (!is_int($key) && !is_string($key)) {
				continue;
			}

			$normalized[(string)$key] = self::normalizePayloadValue($item);
		}

		return $normalized;
	}

	private static function normalizePayloadValue(mixed $value): mixed
	{
		if ($value === null || is_scalar($value)) {
			return $value;
		}

		if (!is_array($value)) {
			return '';
		}

		$normalized = [];

		foreach ($value as $key => $item) {
			if (!is_int($key) && !is_string($key)) {
				continue;
			}

			$normalized[(string)$key] = self::normalizePayloadValue($item);
		}

		return $normalized;
	}

	/**
	 * @param mixed $errors
	 * @return array<string, list<string>>
	 */
	private static function normalizeErrors(mixed $errors): array
	{
		if (!is_array($errors)) {
			return [];
		}

		$normalized = [];

		foreach ($errors as $field => $field_errors) {
			if (!is_int($field) && !is_string($field)) {
				continue;
			}

			if (!is_array($field_errors)) {
				continue;
			}

			foreach ($field_errors as $field_error) {
				if (!is_scalar($field_error)) {
					continue;
				}

				$normalized[(string)$field][] = (string)$field_error;
			}
		}

		return $normalized;
	}

	/**
	 * @param array<string, array<string, mixed>> $bag
	 * @return array<string, array<string, mixed>>
	 */
	private static function limitFlashBag(array $bag): array
	{
		if (count($bag) <= FormSubmitContext::CSRF_TOKEN_BAG_LIMIT) {
			return $bag;
		}

		uasort(
			$bag,
			static fn (array $a, array $b): int => ((int)($a['last_seen_at'] ?? 0) <=> (int)($b['last_seen_at'] ?? 0))
				?: ((int)($a['expires_at'] ?? 0) <=> (int)($b['expires_at'] ?? 0))
		);

		return array_slice($bag, -FormSubmitContext::CSRF_TOKEN_BAG_LIMIT, null, true);
	}

	private static function positiveIntOrNull(mixed $value): ?int
	{
		if ($value === null || $value === '') {
			return null;
		}

		$value = (int)$value;

		return $value > 0 ? $value : null;
	}
}
