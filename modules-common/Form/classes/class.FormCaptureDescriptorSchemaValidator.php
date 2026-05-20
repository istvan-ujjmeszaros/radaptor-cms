<?php

declare(strict_types=1);

final class FormCaptureDescriptorSchemaValidator
{
	public const string CAPTURE_PREFIX = 'capture-';
	public const int MAX_DEFINITION_SLUG_LENGTH = 128;
	public const string DEFAULT_HONEYPOT_FIELD = 'company_website';
	public const int DEFAULT_RATE_LIMIT_ACCEPTED = 5;
	public const int DEFAULT_RATE_LIMIT_WINDOW_SECONDS = 600;

	private const array CAPTURE_FIELD_TYPES = [
		'checkbox',
		'checkboxgroup',
		'date',
		'datetime',
		'radiogroup',
		'select',
		'text',
		'textarea',
	];

	private const array CAPTURE_NORMALIZERS = [
		'trim',
		'lowercase',
		'collapse_whitespace',
	];

	private const array RESERVED_PAYLOAD_KEYS = [
		FormSubmitContext::FIELD_FORM_ID,
		FormSubmitContext::FIELD_FORM_INSTANCE_ID,
		FormSubmitContext::FIELD_ITEM_ID,
		FormSubmitContext::FIELD_RETURN_TARGET,
		FormSubmitContext::FIELD_HOST_PAGE_ID,
		FormSubmitContext::FIELD_WIDGET_CONNECTION_ID,
		FormSubmitContext::FIELD_BUILD_ID,
		FormSubmitContext::FIELD_CONTEXT_PARAMS,
		FormSubmitContext::FIELD_CSRF_TOKEN,
		'submit_button',
	];

	/**
	 * @param array<string, mixed> $descriptor
	 * @param array<string, mixed>|string|null $security
	 */
	public static function validateForDefinition(string $definition_slug, array $descriptor, array|string|null $security = null): void
	{
		self::validateDefinitionSlug($definition_slug);
		$field_keys = self::validateDescriptor($descriptor);
		self::normalizeSecurity($security, $field_keys);
	}

	public static function validateDefinitionSlug(string $definition_slug): void
	{
		if (!preg_match('/^capture-[a-z0-9]+(?:-[a-z0-9]+)*$/D', $definition_slug)) {
			throw new InvalidArgumentException("Capture form definition_slug must use the capture- kebab-case namespace.");
		}

		if (strlen($definition_slug) > self::MAX_DEFINITION_SLUG_LENGTH) {
			throw new InvalidArgumentException('Capture form definition_slug must be 128 characters or shorter.');
		}

		if (FormClassResolver::resolveClassName($definition_slug) !== null) {
			throw new InvalidArgumentException("Capture form definition_slug collides with a system FormType.");
		}
	}

	public static function isCaptureSlug(string $form_id): bool
	{
		return str_starts_with(trim($form_id), self::CAPTURE_PREFIX);
	}

	/**
	 * @param array<string, mixed> $descriptor
	 * @return list<string>
	 */
	public static function validateDescriptor(array $descriptor): array
	{
		self::assertNoUnknownKeys($descriptor, [
			'kind',
			'title',
			'description',
			'sub_title',
			'submit_label',
			'fields',
		], 'descriptor');

		if (($descriptor['kind'] ?? null) !== 'capture') {
			throw new InvalidArgumentException('Capture descriptors must declare kind=capture.');
		}

		foreach (['title', 'description', 'sub_title', 'submit_label'] as $text_key) {
			if (array_key_exists($text_key, $descriptor)) {
				self::assertTextDefinition($descriptor[$text_key], "descriptor.{$text_key}");
			}
		}

		$fields = $descriptor['fields'] ?? null;

		if (!is_array($fields) || $fields === [] || !array_is_list($fields)) {
			throw new InvalidArgumentException('Capture descriptors must contain a non-empty fields list.');
		}

		$seen_names = [];
		$seen_keys = [];

		foreach ($fields as $index => $field) {
			if (!is_array($field)) {
				throw new InvalidArgumentException("Capture descriptor field {$index} must be an object.");
			}

			[$name, $key] = self::validateField($field, $index);

			if (isset($seen_names[$name])) {
				throw new InvalidArgumentException("Duplicate capture descriptor field name '{$name}'.");
			}

			if (isset($seen_keys[$key])) {
				throw new InvalidArgumentException("Duplicate capture descriptor field key '{$key}'.");
			}

			$seen_names[$name] = true;
			$seen_keys[$key] = true;
		}

		return array_keys($seen_keys);
	}

	/**
	 * @param array<string, mixed> $descriptor
	 * @return array<string, mixed>
	 */
	public static function normalizeDescriptor(array $descriptor): array
	{
		self::validateDescriptor($descriptor);

		$normalized = $descriptor;
		$normalized['kind'] = 'capture';
		$normalized_fields = [];

		foreach ($descriptor['fields'] as $field) {
			if (!is_array($field)) {
				continue;
			}

			$normalized_field = $field;
			$normalized_field['type'] = trim((string)($field['type'] ?? 'text'));
			$normalized_field['name'] = trim((string)($field['name'] ?? ''));
			$normalized_field['key'] = trim((string)($field['key'] ?? $normalized_field['name']));

			if (array_key_exists('normalizers', $normalized_field) && is_array($normalized_field['normalizers'])) {
				$normalized_field['normalizers'] = array_values(array_map(
					static fn (mixed $normalizer): string => self::normalizeNormalizerName((string)$normalizer),
					$normalized_field['normalizers'],
				));
			}

			$normalized_fields[] = $normalized_field;
		}

		$normalized['fields'] = $normalized_fields;
		self::validateDescriptor($normalized);

		return $normalized;
	}

	/**
	 * @param array<string, mixed>|string|null $security
	 * @param list<string> $field_keys
	 * @return array{honeypot: array{enabled: true, field_name: string}, rate_limit: array{accepted: int, window_seconds: int}}
	 */
	public static function normalizeSecurity(array|string|null $security = null, array $field_keys = []): array
	{
		if (is_string($security) && trim($security) !== '') {
			try {
				$security = json_decode($security, true, 512, JSON_THROW_ON_ERROR);
			} catch (JsonException $exception) {
				throw new InvalidArgumentException('Capture form security_json must be valid JSON.', 0, $exception);
			}
		}

		if ($security === null || $security === '') {
			$security = [];
		}

		if (!is_array($security)) {
			throw new InvalidArgumentException('Capture form security_json must decode to an object.');
		}

		self::assertNoUnknownKeys($security, ['honeypot', 'rate_limit'], 'security_json');

		$honeypot = self::normalizeHoneypotSecurity($security['honeypot'] ?? []);
		$rate_limit = self::normalizeRateLimitSecurity($security['rate_limit'] ?? []);

		if (in_array($honeypot['field_name'], self::RESERVED_PAYLOAD_KEYS, true) || in_array($honeypot['field_name'], $field_keys, true)) {
			throw new InvalidArgumentException('Capture form honeypot field_name must not collide with form payload keys.');
		}

		return [
			'honeypot' => $honeypot,
			'rate_limit' => $rate_limit,
		];
	}

	/**
	 * @return list<string>
	 */
	public static function getSupportedCaptureFieldTypes(): array
	{
		return self::CAPTURE_FIELD_TYPES;
	}

	/**
	 * @return list<string>
	 */
	public static function getSupportedCaptureNormalizers(): array
	{
		return self::CAPTURE_NORMALIZERS;
	}

	public static function normalizeNormalizerName(string $normalizer): string
	{
		return str_replace('-', '_', trim($normalizer));
	}

	/**
	 * @param array<string, mixed> $field
	 * @return array{string, string}
	 */
	private static function validateField(array $field, int $index): array
	{
		self::assertNoUnknownKeys($field, [
			'type',
			'name',
			'key',
			'label',
			'help',
			'explanation',
			'values',
			'options',
			'required',
			'normalizers',
			'validators',
			'first_in_row',
			'last_in_row',
			'width',
			'height',
			'labelstyle',
			'value',
			'initvalue',
		], "fields[{$index}]");

		$type = trim((string)($field['type'] ?? 'text'));

		if (!in_array($type, self::CAPTURE_FIELD_TYPES, true)) {
			throw new InvalidArgumentException("Unsupported capture descriptor field type '{$type}'.");
		}

		$name = trim((string)($field['name'] ?? ''));

		if (!self::isFieldIdentifier($name)) {
			throw new InvalidArgumentException("Capture descriptor field {$index} has an invalid name.");
		}

		$key = trim((string)($field['key'] ?? $name));

		if (!self::isFieldIdentifier($key)) {
			throw new InvalidArgumentException("Capture descriptor field {$name} has an invalid key.");
		}

		if (in_array($key, self::RESERVED_PAYLOAD_KEYS, true)) {
			throw new InvalidArgumentException("Capture descriptor field key '{$key}' is reserved.");
		}

		foreach (['label', 'help', 'explanation'] as $text_key) {
			if (array_key_exists($text_key, $field)) {
				self::assertTextDefinition($field[$text_key], "field {$name}.{$text_key}");
			}
		}

		if (array_key_exists('normalizers', $field)) {
			self::validateNormalizers($field['normalizers'], $name);
		}

		if (in_array($type, ['select', 'checkboxgroup', 'radiogroup'], true)) {
			self::validateOptions($field['values'] ?? $field['options'] ?? null, $name);
		}

		if (array_key_exists('validators', $field)) {
			self::validateValidators($field['validators'], $name);
		}

		return [$name, $key];
	}

	private static function isFieldIdentifier(string $value): bool
	{
		return preg_match('/^[a-z][a-z0-9_]*$/D', $value) === 1;
	}

	private static function assertTextDefinition(mixed $value, string $path): void
	{
		if (is_scalar($value) || $value === null) {
			return;
		}

		if (!is_array($value)) {
			throw new InvalidArgumentException("Capture descriptor {$path} must be text or an i18n reference.");
		}

		self::assertNoUnknownKeys($value, ['text', 'key', 'params'], $path);

		$has_text = array_key_exists('text', $value) && is_scalar($value['text']);
		$has_key = array_key_exists('key', $value) && is_scalar($value['key']) && trim((string)$value['key']) !== '';

		if (!$has_text && !$has_key) {
			throw new InvalidArgumentException("Capture descriptor {$path} must contain text or key.");
		}

		if (array_key_exists('params', $value) && !is_array($value['params'])) {
			throw new InvalidArgumentException("Capture descriptor {$path}.params must be an object.");
		}

		foreach ((array)($value['params'] ?? []) as $param_key => $param_value) {
			if (!is_string($param_key) || (!is_scalar($param_value) && $param_value !== null)) {
				throw new InvalidArgumentException("Capture descriptor {$path}.params must contain scalar values.");
			}
		}
	}

	private static function validateNormalizers(mixed $normalizers, string $field_name): void
	{
		if (!is_array($normalizers) || !array_is_list($normalizers)) {
			throw new InvalidArgumentException("Capture descriptor field '{$field_name}' normalizers must be a list.");
		}

		foreach ($normalizers as $normalizer) {
			$normalizer = is_scalar($normalizer) ? self::normalizeNormalizerName((string)$normalizer) : '';

			if (!in_array($normalizer, self::CAPTURE_NORMALIZERS, true)) {
				throw new InvalidArgumentException("Unsupported capture descriptor normalizer '{$normalizer}' on field '{$field_name}'.");
			}
		}
	}

	private static function validateOptions(mixed $options, string $field_name): void
	{
		if (!is_array($options) || $options === []) {
			throw new InvalidArgumentException("Capture descriptor field '{$field_name}' requires static options.");
		}

		foreach ($options as $index => $option) {
			if (is_scalar($option)) {
				continue;
			}

			if (!is_array($option)) {
				throw new InvalidArgumentException("Capture descriptor field '{$field_name}' has a non-static option.");
			}

			if (
				(array_key_exists('text', $option) || array_key_exists('key', $option))
				&& !array_key_exists('value', $option)
				&& !array_key_exists('label', $option)
				&& !array_key_exists('inputtype', $option)
			) {
				self::assertTextDefinition($option, "field {$field_name}.options[{$index}]");

				continue;
			}

			self::assertNoUnknownKeys($option, ['inputtype', 'value', 'label'], "field {$field_name}.options[{$index}]");

			if (array_key_exists('value', $option) && !is_scalar($option['value'])) {
				throw new InvalidArgumentException("Capture descriptor field '{$field_name}' option values must be scalar.");
			}

			if (array_key_exists('label', $option)) {
				self::assertTextDefinition($option['label'], "field {$field_name}.options[{$index}].label");
			}
		}
	}

	private static function validateValidators(mixed $validators, string $field_name): void
	{
		if (!is_array($validators) || !array_is_list($validators)) {
			throw new InvalidArgumentException("Capture descriptor field '{$field_name}' validators must be a list.");
		}

		$supported = FormDescriptorValidatorRegistry::getSupportedTypes();

		foreach ($validators as $index => $validator) {
			if (!is_array($validator)) {
				throw new InvalidArgumentException("Capture descriptor validator {$field_name}[{$index}] must be an object.");
			}

			$type = trim((string)($validator['type'] ?? ''));

			if (!in_array($type, $supported, true) || in_array($type, ['file_type', 'file_size'], true)) {
				throw new InvalidArgumentException("Unsupported capture descriptor validator type '{$type}'.");
			}

			self::validateValidatorDefinition($type, $validator, $field_name, $index);
		}
	}

	/**
	 * @param array<string, mixed> $validator
	 */
	private static function validateValidatorDefinition(string $type, array $validator, string $field_name, int $index): void
	{
		$allowed_keys = match ($type) {
			'min_length', 'number_min' => ['type', 'message', 'error_message', 'options', 'min'],
			'max_length', 'number_max' => ['type', 'message', 'error_message', 'options', 'max'],
			'regex' => ['type', 'message', 'error_message', 'options', 'pattern'],
			'enum' => ['type', 'message', 'error_message', 'options', 'values'],
			default => ['type', 'message', 'error_message', 'options'],
		};
		self::assertNoUnknownKeys($validator, $allowed_keys, "field {$field_name}.validators[{$index}]");

		foreach (['message', 'error_message'] as $message_key) {
			if (array_key_exists($message_key, $validator)) {
				self::assertTextDefinition($validator[$message_key], "field {$field_name}.validators[{$index}].{$message_key}");
			}
		}

		if (array_key_exists('options', $validator) && !is_array($validator['options'])) {
			throw new InvalidArgumentException("Capture descriptor validator {$type} options must be an object.");
		}

		$options = is_array($validator['options'] ?? null) ? $validator['options'] : [];
		$options += array_diff_key($validator, array_flip(['type', 'message', 'error_message', 'options']));

		match ($type) {
			'min_length', 'number_min' => self::assertNumericOption($options['min'] ?? null, $type, 'min'),
			'max_length', 'number_max' => self::assertNumericOption($options['max'] ?? null, $type, 'max'),
			'regex' => self::assertRegexOption($options['pattern'] ?? null, $type),
			'enum' => self::assertEnumOption($options['values'] ?? null, $type),
			default => null,
		};
	}

	private static function assertNumericOption(mixed $value, string $type, string $name): void
	{
		if (!is_numeric($value)) {
			throw new InvalidArgumentException("Capture descriptor validator {$type} requires numeric {$name}.");
		}
	}

	private static function assertRegexOption(mixed $value, string $type): void
	{
		if (!is_string($value) || $value === '' || @preg_match($value, '') === false) {
			throw new InvalidArgumentException("Capture descriptor validator {$type} requires a valid regex pattern.");
		}
	}

	private static function assertEnumOption(mixed $value, string $type): void
	{
		if (!is_array($value) || $value === []) {
			throw new InvalidArgumentException("Capture descriptor validator {$type} requires static values.");
		}
	}

	/**
	 * @return array{enabled: true, field_name: string}
	 */
	private static function normalizeHoneypotSecurity(mixed $honeypot): array
	{
		if ($honeypot === null || $honeypot === []) {
			$honeypot = [];
		}

		if (!is_array($honeypot)) {
			throw new InvalidArgumentException('Capture form honeypot security must be an object.');
		}

		self::assertNoUnknownKeys($honeypot, ['enabled', 'field_name'], 'security_json.honeypot');

		if (array_key_exists('enabled', $honeypot) && (bool)$honeypot['enabled'] !== true) {
			throw new InvalidArgumentException('Capture form honeypot security cannot be disabled for public capture forms.');
		}

		$field_name = trim((string)($honeypot['field_name'] ?? self::DEFAULT_HONEYPOT_FIELD));

		if (!self::isFieldIdentifier($field_name)) {
			throw new InvalidArgumentException('Capture form honeypot field_name is invalid.');
		}

		return [
			'enabled' => true,
			'field_name' => $field_name,
		];
	}

	/**
	 * @return array{accepted: int, window_seconds: int}
	 */
	private static function normalizeRateLimitSecurity(mixed $rate_limit): array
	{
		if ($rate_limit === null || $rate_limit === []) {
			$rate_limit = [];
		}

		if (!is_array($rate_limit)) {
			throw new InvalidArgumentException('Capture form rate_limit security must be an object.');
		}

		self::assertNoUnknownKeys($rate_limit, ['accepted', 'window_seconds'], 'security_json.rate_limit');

		$accepted = (int)($rate_limit['accepted'] ?? self::DEFAULT_RATE_LIMIT_ACCEPTED);
		$window_seconds = (int)($rate_limit['window_seconds'] ?? self::DEFAULT_RATE_LIMIT_WINDOW_SECONDS);

		if ($accepted < 1 || $accepted > 100) {
			throw new InvalidArgumentException('Capture form rate_limit.accepted must be between 1 and 100.');
		}

		if ($window_seconds < 60 || $window_seconds > 86400) {
			throw new InvalidArgumentException('Capture form rate_limit.window_seconds must be between 60 and 86400.');
		}

		return [
			'accepted' => $accepted,
			'window_seconds' => $window_seconds,
		];
	}

	/**
	 * @param array<string, mixed> $data
	 * @param list<string> $allowed_keys
	 */
	private static function assertNoUnknownKeys(array $data, array $allowed_keys, string $path): void
	{
		$allowed = array_fill_keys($allowed_keys, true);

		foreach (array_keys($data) as $key) {
			if (!is_string($key) || !isset($allowed[$key])) {
				throw new InvalidArgumentException("Unsupported capture descriptor key '{$key}' at {$path}.");
			}
		}
	}
}
