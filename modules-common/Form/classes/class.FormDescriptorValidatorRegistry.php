<?php

declare(strict_types=1);

final class FormDescriptorValidatorRegistry
{
	private const array SUPPORTED_TYPES = [
		'required',
		'email',
		'url',
		'min_length',
		'max_length',
		'number_min',
		'number_max',
		'regex',
		'enum',
		'date',
		'file_type',
		'file_size',
	];

	/**
	 * @return list<string>
	 */
	public static function getSupportedTypes(): array
	{
		return self::SUPPORTED_TYPES;
	}

	/**
	 * @param array<string, mixed> $definition
	 */
	public static function createValidator(array $definition): FormDescriptorValidator
	{
		$type = trim((string)($definition['type'] ?? ''));

		if (!in_array($type, self::SUPPORTED_TYPES, true)) {
			Kernel::abort("Unsupported form descriptor validator type '{$type}'.");
		}

		$options = self::extractOptions($definition);
		$message = self::resolveMessage($definition['message'] ?? $definition['error_message'] ?? null);

		return new FormDescriptorValidator($type, $message, $options);
	}

	/**
	 * @param array<string, mixed> $options
	 */
	public static function isValid(string $type, mixed $value, array $options = []): bool
	{
		return match ($type) {
			'required' => self::isFilled($value),
			'email' => self::isBlank($value) || (is_scalar($value) && filter_var((string)$value, FILTER_VALIDATE_EMAIL) !== false),
			'url' => self::isBlank($value) || (is_scalar($value) && filter_var((string)$value, FILTER_VALIDATE_URL) !== false),
			'min_length' => self::isBlank($value) || mb_strlen((string)$value) >= self::intOption($options, 'min'),
			'max_length' => self::isBlank($value) || mb_strlen((string)$value) <= self::intOption($options, 'max'),
			'number_min' => self::isBlank($value) || (is_numeric($value) && (float)$value >= self::floatOption($options, 'min')),
			'number_max' => self::isBlank($value) || (is_numeric($value) && (float)$value <= self::floatOption($options, 'max')),
			'regex' => self::isBlank($value) || self::matchesRegex($value, $options),
			'enum' => self::isBlank($value) || self::isInEnum($value, $options),
			'date' => self::isBlank($value) || self::isIsoDate($value),
			'file_type' => self::hasAllowedFileType($value, $options),
			'file_size' => self::hasAllowedFileSize($value, $options),
			default => false,
		};
	}

	/**
	 * @param array<string, mixed> $definition
	 * @return array<string, mixed>
	 */
	private static function extractOptions(array $definition): array
	{
		$options = is_array($definition['options'] ?? null) ? $definition['options'] : [];

		foreach ($definition as $key => $value) {
			if (in_array($key, ['type', 'message', 'error_message', 'options'], true)) {
				continue;
			}

			$options[$key] = $value;
		}

		return $options;
	}

	private static function resolveMessage(mixed $message): string
	{
		if (is_array($message)) {
			if (isset($message['key']) && is_scalar($message['key'])) {
				$params = is_array($message['params'] ?? null) ? $message['params'] : [];

				return t((string)$message['key'], $params);
			}

			if (array_key_exists('text', $message)) {
				return (string)$message['text'];
			}
		}

		if (is_scalar($message) && trim((string)$message) !== '') {
			return (string)$message;
		}

		return t('common.error_save');
	}

	private static function isBlank(mixed $value): bool
	{
		if ($value === null) {
			return true;
		}

		if (is_array($value)) {
			if (self::isFileUploadPayload($value)) {
				return self::isBlankFileUpload($value);
			}

			return count(array_filter($value, static fn (mixed $item): bool => !self::isBlank($item))) === 0;
		}

		return trim(strip_tags((string)$value)) === '';
	}

	private static function isFilled(mixed $value): bool
	{
		return !self::isBlank($value);
	}

	/**
	 * @param array<string, mixed> $value
	 */
	private static function isFileUploadPayload(array $value): bool
	{
		return array_key_exists('error', $value)
			&& (
				array_key_exists('name', $value)
				|| array_key_exists('type', $value)
				|| array_key_exists('tmp_name', $value)
				|| array_key_exists('size', $value)
			);
	}

	/**
	 * @param array<string, mixed> $value
	 */
	private static function isBlankFileUpload(array $value): bool
	{
		$error = $value['error'];

		if (is_array($error)) {
			foreach ($error as $index => $item_error) {
				if (!self::isBlankSingleFileUpload($value, $index, $item_error)) {
					return false;
				}
			}

			return true;
		}

		return self::isBlankSingleFileUpload($value, null, $error);
	}

	/**
	 * @param array<string, mixed> $value
	 */
	private static function isBlankSingleFileUpload(array $value, int|string|null $index, mixed $error): bool
	{
		if ((int)$error !== UPLOAD_ERR_OK) {
			return true;
		}

		$name = self::indexedUploadValue($value['name'] ?? null, $index);
		$tmp_name = self::indexedUploadValue($value['tmp_name'] ?? null, $index);

		return self::isBlank($name) && self::isBlank($tmp_name);
	}

	private static function indexedUploadValue(mixed $value, int|string|null $index): mixed
	{
		if ($index === null || !is_array($value)) {
			return $value;
		}

		return $value[$index] ?? null;
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private static function intOption(array $options, string $key): int
	{
		return (int)($options[$key] ?? 0);
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private static function floatOption(array $options, string $key): float
	{
		return (float)($options[$key] ?? 0);
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private static function matchesRegex(mixed $value, array $options): bool
	{
		$pattern = is_scalar($options['pattern'] ?? null) ? (string)$options['pattern'] : '';

		if ($pattern === '') {
			return false;
		}

		return @preg_match($pattern, (string)$value) === 1;
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private static function isInEnum(mixed $value, array $options): bool
	{
		$values = $options['values'] ?? [];

		if (!is_array($values)) {
			return false;
		}

		if (is_array($value)) {
			foreach ($value as $key => $item) {
				if (self::isBlank($item)) {
					continue;
				}

				$candidate = array_is_list($value) ? $item : $key;

				if (!self::enumContains($candidate, $values)) {
					return false;
				}
			}

			return true;
		}

		return self::enumContains($value, $values);
	}

	/**
	 * @param array<mixed> $values
	 */
	private static function enumContains(mixed $value, array $values): bool
	{
		if (!is_scalar($value)) {
			return false;
		}

		foreach ($values as $key => $allowed) {
			if ((string)$key === (string)$value) {
				return true;
			}

			if (is_array($allowed) && array_key_exists('value', $allowed)) {
				$allowed = $allowed['value'];
			}

			if (is_scalar($allowed) && (string)$allowed === (string)$value) {
				return true;
			}
		}

		return false;
	}

	private static function isIsoDate(mixed $value): bool
	{
		if (!is_scalar($value) || preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', (string)$value, $match) !== 1) {
			return false;
		}

		return checkdate((int)$match[2], (int)$match[3], (int)$match[1]);
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private static function hasAllowedFileType(mixed $value, array $options): bool
	{
		if (self::hasFileUploadError($value)) {
			return false;
		}

		$files = self::normalizeFileValues($value);

		if ($files === []) {
			return true;
		}

		$mime_types = is_array($options['mime_types'] ?? null) ? $options['mime_types'] : ($options['types'] ?? []);
		$extensions = is_array($options['extensions'] ?? null) ? $options['extensions'] : [];

		foreach ($files as $file) {
			if (!self::isAllowedFileType($file, is_array($mime_types) ? $mime_types : [], $extensions)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private static function hasAllowedFileSize(mixed $value, array $options): bool
	{
		if (self::hasFileUploadError($value)) {
			return false;
		}

		$files = self::normalizeFileValues($value);

		if ($files === []) {
			return true;
		}

		$max_bytes = (int)($options['max_bytes'] ?? $options['max'] ?? 0);

		if ($max_bytes <= 0) {
			return false;
		}

		foreach ($files as $file) {
			if ((int)($file['size'] ?? 0) > $max_bytes) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param list<mixed> $mime_types
	 * @param list<mixed> $extensions
	 */
	private static function isAllowedFileType(array $file, array $mime_types, array $extensions): bool
	{
		$mime_type = strtolower((string)($file['type'] ?? $file['mime'] ?? ''));
		$file_name = (string)($file['name'] ?? $file['original_name'] ?? '');
		$extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

		foreach ($mime_types as $allowed) {
			if ($mime_type !== '' && strtolower((string)$allowed) === $mime_type) {
				return true;
			}
		}

		foreach ($extensions as $allowed) {
			if ($extension !== '' && ltrim(strtolower((string)$allowed), '.') === $extension) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function normalizeFileValues(mixed $value): array
	{
		if (!is_array($value)) {
			return [];
		}

		if (is_array($value['error'] ?? null)) {
			$files = [];

			foreach ($value['error'] as $index => $error) {
				if ((int)$error !== UPLOAD_ERR_OK) {
					continue;
				}

				$files[] = [
					'name' => self::indexedUploadValue($value['name'] ?? null, $index),
					'type' => self::indexedUploadValue($value['type'] ?? null, $index),
					'tmp_name' => self::indexedUploadValue($value['tmp_name'] ?? null, $index),
					'size' => self::indexedUploadValue($value['size'] ?? null, $index),
					'error' => $error,
				];
			}

			return $files;
		}

		$error = (int)($value['error'] ?? UPLOAD_ERR_OK);

		if ($error !== UPLOAD_ERR_OK) {
			return [];
		}

		return [self::normalizeSingleFileValue($value)];
	}

	/**
	 * @param array<string, mixed> $value
	 * @return array<string, mixed>
	 */
	private static function normalizeSingleFileValue(array $value): array
	{
		$path = (string)($value['tmp_name'] ?? $value['path'] ?? '');

		return [
			'name' => $value['name'] ?? $value['original_name'] ?? '',
			'type' => $value['type'] ?? $value['mime'] ?? '',
			'tmp_name' => $path,
			'size' => $value['size'] ?? (is_file($path) ? filesize($path) : null),
			'error' => $value['error'] ?? UPLOAD_ERR_OK,
		];
	}

	private static function hasFileUploadError(mixed $value): bool
	{
		if (!is_array($value) || !array_key_exists('error', $value)) {
			return false;
		}

		if (is_array($value['error'])) {
			foreach ($value['error'] as $error) {
				if (!in_array((int)$error, [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE], true)) {
					return true;
				}
			}

			return false;
		}

		$error = (int)$value['error'];

		return !in_array($error, [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE], true);
	}
}
