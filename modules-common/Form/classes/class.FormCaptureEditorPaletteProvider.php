<?php

declare(strict_types=1);

final class FormCaptureEditorPaletteProvider implements iEditorPaletteItemProvider
{
	public const string TARGET_FIELDS = 'capture_form.fields';

	/**
	 * @return list<EditorPaletteItem>
	 */
	public function getPaletteItems(): array
	{
		return [
			$this->item('text', t('form.builder.field.text'), 'type'),
			$this->item('textarea', t('form.builder.field.textarea'), 'textarea-t'),
			$this->item('select', t('form.builder.field.select'), 'menu-button-wide'),
			$this->item('radiogroup', t('form.builder.field.radiogroup'), 'record-circle'),
			$this->item('checkbox', t('form.builder.field.checkbox'), 'check-square'),
			$this->item('checkboxgroup', t('form.builder.field.checkboxgroup'), 'ui-checks'),
			$this->item('date', t('form.builder.field.date'), 'calendar-date'),
			$this->item('datetime', t('form.builder.field.datetime'), 'calendar-event'),
		];
	}

	/**
	 * @return list<EditorDropTarget>
	 */
	public function getDropTargets(): array
	{
		return [
			new EditorDropTarget(
				self::TARGET_FIELDS,
				t('form.builder.drop_target.fields'),
				FormCaptureDescriptorSchemaValidator::getSupportedCaptureFieldTypes(),
			),
		];
	}

	private function item(string $type, string $label, string $icon): EditorPaletteItem
	{
		return new EditorPaletteItem(
			$type,
			$label,
			$icon,
			[self::TARGET_FIELDS],
			$this->defaultsForType($type),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function defaultsForType(string $type): array
	{
		$defaults = [
			'type' => $type,
			'name' => $type,
			'key' => $type,
			'label' => ['text' => $this->defaultLabelForType($type)],
		];

		if (in_array($type, ['select', 'radiogroup', 'checkboxgroup'], true)) {
			$defaults['values'] = [
				['inputtype' => 'option', 'value' => 'option_1', 'label' => ['text' => t('form.builder.option.default_one')]],
				['inputtype' => 'option', 'value' => 'option_2', 'label' => ['text' => t('form.builder.option.default_two')]],
			];
			$defaults['validators'] = [
				['type' => 'enum', 'values' => ['option_1', 'option_2']],
			];
		}

		if (in_array($type, ['text', 'textarea'], true)) {
			$defaults['normalizers'] = ['trim'];
		}

		return $defaults;
	}

	private function defaultLabelForType(string $type): string
	{
		return match ($type) {
			'textarea' => t('form.builder.field.textarea'),
			'select' => t('form.builder.field.select'),
			'radiogroup' => t('form.builder.field.radiogroup'),
			'checkbox' => t('form.builder.field.checkbox'),
			'checkboxgroup' => t('form.builder.field.checkboxgroup'),
			'date' => t('form.builder.field.date'),
			'datetime' => t('form.builder.field.datetime'),
			default => t('form.builder.field.text'),
		};
	}
}
