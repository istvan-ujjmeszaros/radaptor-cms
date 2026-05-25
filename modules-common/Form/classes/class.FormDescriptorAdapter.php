<?php

declare(strict_types=1);

final class FormDescriptorAdapter
{
	/** @var array<string, class-string<FormInput>> */
	private const array INPUT_CLASSES = [
		'checkbox' => FormInputCheckbox::class,
		'checkboxgroup' => FormInputCheckboxgroup::class,
		'clearfloat' => FormInputClearFloat::class,
		'date' => FormInputDate::class,
		'datetime' => FormInputDateTime::class,
		'groupend' => FormInputGroupEnd::class,
		'hidden' => FormInputHidden::class,
		'linkgroup' => FormInputLinkGroup::class,
		'password' => FormInputPassword::class,
		'radiogroup' => FormInputRadiogroup::class,
		'select' => FormInputSelect::class,
		'text' => FormInputText::class,
		'textarea' => FormInputTextarea::class,
		'widgetgroupbeginning' => FormInputWidgetGroupBeginning::class,
	];

	/**
	 * @param array<string, mixed> $descriptor
	 */
	public static function buildInputs(AbstractForm $form, array $descriptor): void
	{
		$fields = $descriptor['fields'] ?? [];

		if (!is_array($fields)) {
			Kernel::abort('Form descriptor fields must be an array. (' . $form->getFormType() . ')');
		}

		foreach ($fields as $field) {
			if (!is_array($field)) {
				Kernel::abort('Form descriptor field entries must be arrays. (' . $form->getFormType() . ')');
			}

			self::buildInput($form, $field);
		}
	}

	/**
	 * @param array<string, mixed> $field
	 */
	private static function buildInput(AbstractForm $form, array $field): FormInput
	{
		$type = trim((string)($field['type'] ?? FormInputText::INPUTTYPE));
		$name = trim((string)($field['name'] ?? $field['fieldname'] ?? ''));

		if ($name === '') {
			Kernel::abort('Form descriptor field is missing a name. (' . $form->getFormType() . ')');
		}

		$class_name = self::INPUT_CLASSES[$type] ?? null;

		if ($class_name === null) {
			Kernel::abort("Unsupported form descriptor field type '{$type}'. (" . $form->getFormType() . ')');
		}

		$input = new $class_name($name, $form);
		self::applyCommonProps($input, $field);
		self::applyTypeSpecificProps($input, $field);
		self::applyValidators($input, $field['validators'] ?? []);

		if (!array_key_exists($input->fieldname, $form->initvalues)) {
			if (array_key_exists('initvalue', $field)) {
				$input->initvalue = $field['initvalue'];
			}

			if (array_key_exists('value', $field)) {
				$input->setValue($field['value']);
			}
		}

		return $input;
	}

	/**
	 * @param array<string, mixed> $field
	 */
	private static function applyCommonProps(FormInput $input, array $field): void
	{
		if (array_key_exists('key', $field)) {
			$input->key = trim((string)$field['key']);
		}

		if (array_key_exists('label', $field)) {
			$input->label = self::resolveText($field['label']);
		}

		if (array_key_exists('help', $field)) {
			$input->explanation = self::resolveText($field['help']);
		} elseif (array_key_exists('explanation', $field)) {
			$input->explanation = self::resolveText($field['explanation']);
		}

		foreach ([
			'save' => 'save',
			'readonly' => 'readonly',
			'first_in_row' => 'first_in_row',
			'last_in_row' => 'last_in_row',
		] as $descriptor_key => $property) {
			if (array_key_exists($descriptor_key, $field)) {
				$input->{$property} = (bool)$field[$descriptor_key];
			}
		}

		foreach ([
			'width' => 'width',
			'height' => 'height',
			'labelstyle' => 'labelstyle',
		] as $descriptor_key => $property) {
			if (array_key_exists($descriptor_key, $field)) {
				$input->{$property} = (string)$field[$descriptor_key];
			}
		}
	}

	/**
	 * @param array<string, mixed> $field
	 */
	private static function applyTypeSpecificProps(FormInput $input, array $field): void
	{
		if ($input instanceof FormInputSelect) {
			$input->values = self::resolveValues($field['values'] ?? $field['options'] ?? []);

			if (array_key_exists('required', $field)) {
				$input->required = (bool)$field['required'];
			}
		}

		if ($input instanceof FormInputCheckboxgroup) {
			$input->values = self::resolveCheckboxGroupValues($field['values'] ?? $field['options'] ?? []);
		}

		if ($input instanceof FormInputRadiogroup) {
			$input->values = self::resolveRadioGroupValues($field['values'] ?? $field['options'] ?? []);
		}

		if ($input instanceof FormInputLinkGroup) {
			$input->values = self::resolveValues($field['values'] ?? $field['options'] ?? []);
		}

		if ($input instanceof FormInputTextarea) {
			if (array_key_exists('editor', $field)) {
				$input->editor = (string)$field['editor'];
			}

			if (array_key_exists('toolbar', $field)) {
				$input->toolbar = (string)$field['toolbar'];
			}
		}

		if ($input instanceof FormInputText) {
			if (array_key_exists('autocomplete_url', $field)) {
				$input->autocomplete_url = (string)$field['autocomplete_url'];
			}

			if (array_key_exists('connected_autocomplete_fieldname', $field)) {
				$input->connected_autocomplete_fieldname = (string)$field['connected_autocomplete_fieldname'];
			}
		}

		if ($input instanceof FormInputWidgetGroupBeginning && array_key_exists('class', $field)) {
			$input->class = (string)$field['class'];
		}
	}

	/**
	 * @param mixed $validators
	 */
	private static function applyValidators(FormInput $input, mixed $validators): void
	{
		if ($validators === null || $validators === []) {
			return;
		}

		if (!is_array($validators)) {
			Kernel::abort('Form descriptor validators must be an array. (' . $input->fieldname . ')');
		}

		foreach ($validators as $validator) {
			if (!is_array($validator)) {
				Kernel::abort('Form descriptor validator entries must be arrays. (' . $input->fieldname . ')');
			}

			$input->addValidator(FormDescriptorValidatorRegistry::createValidator($validator));
		}
	}

	private static function resolveText(mixed $value): string
	{
		if (is_array($value)) {
			if (isset($value['key']) && is_scalar($value['key'])) {
				$params = is_array($value['params'] ?? null) ? $value['params'] : [];

				return t((string)$value['key'], $params);
			}

			if (array_key_exists('text', $value)) {
				return (string)$value['text'];
			}
		}

		return (string)$value;
	}

	/**
	 * @return array<mixed>
	 */
	private static function resolveValues(mixed $values): array
	{
		if (!is_array($values)) {
			return [];
		}

		$resolved = [];

		foreach ($values as $key => $value) {
			if (is_array($value)) {
				if (
					(array_key_exists('text', $value) || array_key_exists('key', $value))
					&& !array_key_exists('value', $value)
					&& !array_key_exists('label', $value)
					&& !array_key_exists('inputtype', $value)
				) {
					$resolved[$key] = self::resolveText($value);

					continue;
				}

				$entry = $value;

				if (array_key_exists('label', $entry)) {
					$entry['label'] = self::resolveText($entry['label']);
				}

				$resolved[$key] = $entry;

				continue;
			}

			$resolved[$key] = self::resolveText($value);
		}

		return $resolved;
	}

	/**
	 * @return array<string, string>
	 */
	private static function resolveCheckboxGroupValues(mixed $values): array
	{
		$resolved = [];

		foreach (self::resolveChoiceOptions($values) as $option) {
			$resolved[$option['value']] = $option['label'];
		}

		return $resolved;
	}

	/**
	 * Legacy radiogroup templates expect label => value.
	 *
	 * @return array<string, string>
	 */
	private static function resolveRadioGroupValues(mixed $values): array
	{
		$resolved = [];

		if (!is_array($values)) {
			return $resolved;
		}

		foreach ($values as $key => $value) {
			if (is_string($key) && !is_array($value)) {
				$option_value = self::resolveText($value);

				if ($option_value !== '') {
					$resolved[$key] = $option_value;
				}

				continue;
			}

			foreach (self::resolveChoiceOptions([$key => $value]) as $option) {
				$resolved[$option['label']] = $option['value'];
			}
		}

		return $resolved;
	}

	/**
	 * @return list<array{value: string, label: string}>
	 */
	private static function resolveChoiceOptions(mixed $values): array
	{
		if (!is_array($values)) {
			return [];
		}

		$resolved = [];

		foreach ($values as $key => $value) {
			if (is_array($value)) {
				if (
					(array_key_exists('text', $value) || array_key_exists('key', $value))
					&& !array_key_exists('value', $value)
					&& !array_key_exists('label', $value)
					&& !array_key_exists('inputtype', $value)
				) {
					$option_value = is_string($key) ? $key : self::resolveText($value);
					$option_label = self::resolveText($value);
				} else {
					$option_value = (string)($value['value'] ?? (is_string($key) ? $key : ''));
					$option_label = self::resolveText($value['label'] ?? $option_value);
				}
			} else {
				$option_value = is_string($key) ? $key : self::resolveText($value);
				$option_label = self::resolveText($value);
			}

			if ($option_value === '') {
				continue;
			}

			$resolved[] = [
				'value' => $option_value,
				'label' => $option_label,
			];
		}

		return $resolved;
	}
}
