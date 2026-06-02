<?php

declare(strict_types=1);

final class FormCaptureFieldPropertyProvider
{
	public const string MODE_BUILDER = 'builder';
	public const string MODE_EDITMODE = 'editmode';

	private const array OPTION_FIELD_TYPES = [
		'select',
		'radiogroup',
		'checkboxgroup',
	];

	/**
	 * @return list<array<string, mixed>>
	 */
	public function getProperties(): array
	{
		return [
			[
				'id' => 'label',
				'label_key' => 'form.builder.label.field_label',
				'control' => 'text',
				'post_name' => 'field_label',
				'builder_target' => 'fieldLabelInput',
				'builder_action' => 'input->form-builder#updateSelectedField',
				'i18n_target' => 'fieldLabelTranslationLink',
			],
			[
				'id' => 'name',
				'label_key' => 'form.builder.label.field_name',
				'control' => 'text',
				'post_name' => 'field_name',
				'builder_target' => 'fieldNameInput',
				'builder_action' => 'input->form-builder#updateSelectedField',
			],
			[
				'id' => 'key',
				'label_key' => 'form.builder.label.field_key',
				'control' => 'text',
				'post_name' => 'field_key_new',
				'builder_target' => 'fieldKeyInput',
				'builder_action' => 'change->form-builder#confirmAndUpdateFieldKey',
			],
			[
				'id' => 'required',
				'label_key' => 'form.builder.label.required',
				'control' => 'checkbox',
				'post_name' => 'field_required',
				'builder_target' => 'fieldRequiredInput',
				'builder_action' => 'change->form-builder#updateSelectedField',
				'i18n_target' => 'fieldRequiredTranslationLink',
			],
			[
				'id' => 'options',
				'label_key' => 'form.builder.label.options',
				'control' => 'textarea',
				'post_name' => 'field_options',
				'builder_target' => 'fieldOptionsInput',
				'builder_action' => 'input->form-builder#updateSelectedField',
				'builder_group_target' => 'fieldOptionsGroup',
				'i18n_target' => 'fieldOptionsTranslationLink',
				'visible_for' => self::OPTION_FIELD_TYPES,
				'rows' => 5,
			],
		];
	}

	/**
	 * @return array<string, string>
	 */
	public function getStrings(): array
	{
		$keys = [
			'form.builder.label.field_label',
			'form.builder.label.field_name',
			'form.builder.label.field_key',
			'form.builder.label.required',
			'form.builder.label.options',
			'form.builder.action.open_translations',
			'form.field_edit.button',
			'form.field_edit.icon_title',
			'form.field_edit.title',
			'form.field_edit.action.save',
			'form.field_edit.action.close',
		];
		$strings = [];

		foreach ($keys as $key) {
			$strings[$key] = t($key);
		}

		return $strings;
	}

	/**
	 * @param array<string, mixed> $field
	 * @return array<string, mixed>
	 */
	public function valuesForField(array $field): array
	{
		return [
			'label' => self::textValue($field['label'] ?? ''),
			'name' => (string)($field['name'] ?? ''),
			'key' => (string)($field['key'] ?? $field['name'] ?? ''),
			'required' => self::hasValidator($field, 'required'),
			'options' => $this->optionsText($field),
		];
	}

	/**
	 * @param array<string, mixed> $property
	 * @param array<string, mixed> $field
	 */
	public function propertyAppliesToField(array $property, array $field): bool
	{
		$visible_for = $property['visible_for'] ?? null;

		if (!is_array($visible_for)) {
			return true;
		}

		return in_array((string)($field['type'] ?? ''), $visible_for, true);
	}

	/**
	 * @param array<string, mixed> $descriptor
	 * @param array<string, mixed> $field
	 * @param array<string, mixed> $submitted
	 * @param list<array<string, mixed>> $all_fields
	 * @return array<string, mixed>
	 */
	public function applySubmittedValues(
		string $definition_slug,
		array $descriptor,
		array $field,
		array $submitted,
		array $all_fields,
		int $field_offset,
	): array {
		$next_field = $field;
		$next_name = self::identifier((string)($submitted['field_name'] ?? $field['name'] ?? 'field'));
		$next_key = self::identifier((string)($submitted['field_key_new'] ?? $field['key'] ?? $next_name));
		$this->assertUniqueIdentifier($all_fields, $field_offset, 'name', $next_name);
		$this->assertUniqueIdentifier($all_fields, $field_offset, 'key', $next_key);
		$next_field['name'] = $next_name;
		$next_field['key'] = $next_key;
		$next_field['label'] = $this->textDefinitionForField(
			$definition_slug,
			$descriptor,
			$next_field,
			'label',
			(string)($submitted['field_label'] ?? self::textValue($field['label'] ?? '')),
		);
		$next_field['validators'] = $this->syncRequiredValidator(
			is_array($next_field['validators'] ?? null) ? $next_field['validators'] : [],
			!empty($submitted['field_required']),
		);

		if (self::isOptionFieldType((string)($next_field['type'] ?? ''))) {
			$next_field['values'] = $this->parseOptionsText(
				$definition_slug,
				$descriptor,
				$next_field,
				(string)($submitted['field_options'] ?? $this->optionsText($field)),
				$field,
			);
			$next_field['validators'] = $this->syncEnumValidator(
				is_array($next_field['validators'] ?? null) ? $next_field['validators'] : [],
				array_map(static fn (array $option): string => (string)$option['value'], $next_field['values']),
			);
		}

		return $next_field;
	}

	/**
	 * @param array<string, mixed> $field
	 */
	public static function isOptionField(array $field): bool
	{
		return self::isOptionFieldType((string)($field['type'] ?? ''));
	}

	public static function isOptionFieldType(string $type): bool
	{
		return in_array($type, self::OPTION_FIELD_TYPES, true);
	}

	public static function textValue(mixed $value): string
	{
		if (is_scalar($value) || $value === null) {
			return (string)$value;
		}

		if (is_array($value)) {
			if (array_key_exists('text', $value) && is_scalar($value['text'])) {
				return (string)$value['text'];
			}

			if (array_key_exists('key', $value) && is_scalar($value['key']) && trim((string)$value['key']) !== '') {
				return t((string)$value['key']);
			}
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $field
	 */
	private static function hasValidator(array $field, string $type): bool
	{
		foreach (($field['validators'] ?? []) as $validator) {
			if (is_array($validator) && (string)($validator['type'] ?? '') === $type) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $field
	 */
	private function optionsText(array $field): string
	{
		$lines = [];

		foreach ($this->optionEntries($field) as $option) {
			$lines[] = (string)$option['value'] . '=' . self::textValue($option['label'] ?? '');
		}

		return implode("\n", $lines);
	}

	/**
	 * @param array<string, mixed> $field
	 * @return list<array<string, mixed>>
	 */
	private function optionEntries(array $field): array
	{
		$options = $field['values'] ?? $field['options'] ?? [];

		if (!is_array($options)) {
			return [];
		}

		$entries = [];

		if (array_is_list($options)) {
			foreach ($options as $option) {
				if (is_scalar($option)) {
					$entries[] = [
						'inputtype' => 'option',
						'value' => (string)$option,
						'label' => ['text' => (string)$option],
					];

					continue;
				}

				if (is_array($option)) {
					$entries[] = [
						'inputtype' => 'option',
						'value' => (string)($option['value'] ?? self::textValue($option['label'] ?? '')),
						'label' => $option['label'] ?? ['text' => self::textValue($option)],
					];
				}
			}

			return $entries;
		}

		foreach ($options as $key => $label) {
			$entries[] = [
				'inputtype' => 'option',
				'value' => (string)$key,
				'label' => ['text' => self::textValue($label)],
			];
		}

		return $entries;
	}

	/**
	 * @param list<array<string, mixed>> $validators
	 * @return list<array<string, mixed>>
	 */
	private function syncRequiredValidator(array $validators, bool $required): array
	{
		$validators = array_values(array_filter(
			$validators,
			static fn (mixed $validator): bool => !is_array($validator) || (string)($validator['type'] ?? '') !== 'required',
		));

		if ($required) {
			array_unshift($validators, ['type' => 'required']);
		}

		return $validators;
	}

	/**
	 * @param list<array<string, mixed>> $validators
	 * @param list<string> $values
	 * @return list<array<string, mixed>>
	 */
	private function syncEnumValidator(array $validators, array $values): array
	{
		$validators = array_values(array_filter(
			$validators,
			static fn (mixed $validator): bool => !is_array($validator) || (string)($validator['type'] ?? '') !== 'enum',
		));
		$validators[] = [
			'type' => 'enum',
			'values' => $values,
		];

		return $validators;
	}

	/**
	 * @param array<string, mixed> $descriptor
	 * @param array<string, mixed> $field
	 * @param array<string, mixed> $old_field
	 * @return list<array<string, mixed>>
	 */
	private function parseOptionsText(string $definition_slug, array $descriptor, array $field, string $value, array $old_field): array
	{
		$old_options_by_value = [];

		foreach ($this->optionEntries($old_field) as $old_option) {
			$old_options_by_value[(string)$old_option['value']] = $old_option;
		}

		$options = [];

		foreach (preg_split('/\r?\n/', $value) ?: [] as $line) {
			$line = trim((string)$line);

			if ($line === '') {
				continue;
			}

			$parts = explode('=', $line, 2);
			$option_value = self::identifier(trim($parts[0]));
			$label_text = trim($parts[1] ?? $parts[0]);
			$option = [
				'inputtype' => 'option',
				'value' => $option_value,
				'label' => $this->textDefinitionForOption(
					$definition_slug,
					$descriptor,
					$field,
					$option_value,
					$label_text,
					$old_options_by_value[$option_value]['label'] ?? null,
				),
			];
			$options[] = $option;
		}

		return $options;
	}

	/**
	 * @param array<string, mixed> $descriptor
	 * @param array<string, mixed> $field
	 * @return array<string, string>
	 */
	private function textDefinitionForField(string $definition_slug, array $descriptor, array $field, string $property, string $text): array
	{
		$old_value = $field[$property] ?? null;
		$key = is_array($old_value) && is_scalar($old_value['key'] ?? null) ? trim((string)$old_value['key']) : '';

		if ($this->usesKeyedI18n($descriptor)) {
			$key = FormCaptureDescriptorSchemaValidator::i18nKeyPrefixForDefinition($definition_slug)
				. '.fields.' . self::identifier((string)($field['key'] ?? $field['name'] ?? 'field'))
				. '.' . self::identifier($property);
		}

		return $key !== '' ? ['key' => $key, 'text' => $text] : ['text' => $text];
	}

	/**
	 * @param array<string, mixed> $descriptor
	 * @param array<string, mixed> $field
	 * @return array<string, string>
	 */
	private function textDefinitionForOption(
		string $definition_slug,
		array $descriptor,
		array $field,
		string $option_value,
		string $text,
		mixed $old_label,
	): array {
		$key = is_array($old_label) && is_scalar($old_label['key'] ?? null) ? trim((string)$old_label['key']) : '';

		if ($this->usesKeyedI18n($descriptor)) {
			$key = FormCaptureDescriptorSchemaValidator::i18nKeyPrefixForDefinition($definition_slug)
				. '.fields.' . self::identifier((string)($field['key'] ?? $field['name'] ?? 'field'))
				. '.options.' . self::identifier($option_value)
				. '.label';
		}

		return $key !== '' ? ['key' => $key, 'text' => $text] : ['text' => $text];
	}

	/**
	 * @param array<string, mixed> $descriptor
	 */
	private function usesKeyedI18n(array $descriptor): bool
	{
		return ($descriptor['i18n_mode'] ?? FormCaptureDescriptorSchemaValidator::I18N_MODE_LITERAL) === FormCaptureDescriptorSchemaValidator::I18N_MODE_KEYED;
	}

	/**
	 * @param list<array<string, mixed>> $fields
	 */
	private function assertUniqueIdentifier(array $fields, int $current_offset, string $key, string $value): void
	{
		foreach ($fields as $offset => $field) {
			if ($offset === $current_offset || !is_array($field)) {
				continue;
			}

			$existing = trim((string)($field[$key] ?? ($key === 'key' ? ($field['name'] ?? '') : '')));

			if ($existing === $value) {
				throw new InvalidArgumentException("Duplicate capture descriptor field {$key} '{$value}'.");
			}
		}
	}

	private static function identifier(string $value): string
	{
		$normalized = strtolower(trim($value));
		$normalized = (string)preg_replace('/[^a-z0-9]+/', '_', $normalized);
		$normalized = trim($normalized, '_');

		return $normalized !== '' && preg_match('/^[a-z]/', $normalized) === 1 ? $normalized : 'field_' . ($normalized !== '' ? $normalized : '1');
	}
}
