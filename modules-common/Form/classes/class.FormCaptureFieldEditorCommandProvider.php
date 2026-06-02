<?php

declare(strict_types=1);

final class FormCaptureFieldEditorCommandProvider implements iFormEditorFieldCommandProvider
{
	/**
	 * @return list<FormEditorFieldCommand>
	 */
	public function getCommands(FormEditorFieldCommandContext $context): array
	{
		$strings = $this->getStrings();
		$payload = $this->basePayload($context);
		$field_label = FormCaptureFieldPropertyProvider::textValue($context->field['label'] ?? $context->fieldKey);
		$delete_title = $strings['common.delete'];
		$delete_message = trim($delete_title . ($field_label !== '' ? ': ' . $field_label : ''));

		return [
			new FormEditorFieldCommand(
				id: 'edit',
				title: $strings['form.field_edit.icon_title'],
				icon: IconNames::EDIT,
				action: FormEditorFieldCommand::ACTION_PANEL,
				panelId: $context->panelId,
			),
			new FormEditorFieldCommand(
				id: 'move_up',
				title: $strings['common.move_up'],
				icon: IconNames::WIDGET_UP,
				action: FormEditorFieldCommand::ACTION_HTMX,
				url: Url::getUrl('form_editor.move_field'),
				payload: $payload + ['direction' => 'up'],
				disabled: $context->fieldIndex <= 0,
			),
			new FormEditorFieldCommand(
				id: 'move_down',
				title: $strings['common.move_down'],
				icon: IconNames::WIDGET_DOWN,
				action: FormEditorFieldCommand::ACTION_HTMX,
				url: Url::getUrl('form_editor.move_field'),
				payload: $payload + ['direction' => 'down'],
				disabled: $context->fieldIndex >= $context->visibleFieldCount - 1,
			),
			new FormEditorFieldCommand(
				id: 'delete',
				title: $delete_title,
				icon: IconNames::WIDGET_REMOVE,
				action: FormEditorFieldCommand::ACTION_HTMX,
				url: Url::getUrl('form_editor.remove_field'),
				payload: $payload,
				variant: FormEditorFieldCommand::VARIANT_DANGER,
				disabled: $context->visibleFieldCount <= 1,
				confirmTitle: $delete_title,
				confirmMessage: $delete_message !== '' ? $delete_message : $delete_title,
				confirmLabel: $strings['common.delete'],
				cancelLabel: $strings['common.cancel'],
			),
		];
	}

	/**
	 * @return array<string, string>
	 */
	public function getStrings(): array
	{
		$keys = [
			'form.field_edit.icon_title',
			'common.move_up',
			'common.move_down',
			'common.delete',
			'common.cancel',
		];
		$strings = [];

		foreach ($keys as $key) {
			$strings[$key] = t($key);
		}

		return $strings;
	}

	/**
	 * @return array<string, scalar|null>
	 */
	private function basePayload(FormEditorFieldCommandContext $context): array
	{
		$payload = [];

		foreach ($context->target as $name => $value) {
			if (is_scalar($value) || $value === null) {
				$payload[(string)$name] = $value;
			}
		}

		$payload[FormSubmitContext::FIELD_CSRF_TOKEN] = FormSubmitContext::issueCsrfTokenForForm(FormBuilderEventHelper::CSRF_INLINE_FIELD_COMMAND_FORM_ID);
		$payload['field_uid'] = $context->fieldUid;
		$payload['field_key'] = $context->fieldKey;
		$payload['field_index'] = $context->fieldIndex;

		return $payload;
	}
}
