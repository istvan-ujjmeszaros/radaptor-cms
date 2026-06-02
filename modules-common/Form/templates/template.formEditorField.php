<?php assert(isset($this) && $this instanceof Template); ?>
<?php
library('__ADMIN_EDIT_MODE');

$panelId = (string)($this->props['panel_id'] ?? '');
$targetId = (string)($this->props['target_id'] ?? '');
$fieldUid = (string)($this->props['field_uid'] ?? '');
$fieldKey = (string)($this->props['field_key'] ?? '');
$fieldIndex = (int)($this->props['field_index'] ?? 0);
$label = (string)($this->props['field_label'] ?? $fieldKey);
$title = (string)($this->strings['form.field_edit.icon_title'] ?? '');
?>
<div
	<?= $targetId !== '' ? 'id="' . e($targetId) . '"' : '' ?>
	class="form-editor-field"
	data-form-editor-field
	data-form-editor-field-panel-id="<?= e($panelId) ?>"
	data-form-editor-field-uid="<?= e($fieldUid) ?>"
	data-form-editor-field-key="<?= e($fieldKey) ?>"
	data-form-editor-field-index="<?= e((string)$fieldIndex) ?>"
>
	<div class="form-editor-field__toolbar">
		<button
			type="button"
			class="form-editor-field__edit-button"
			data-form-editor-field-toggle
			aria-controls="<?= e($panelId) ?>"
			aria-expanded="false"
			aria-label="<?= e($title) ?>"
			title="<?= e($title) ?>"
		>
			<?= Icons::get(IconNames::EDIT, $title) ?>
		</button>
	</div>
	<div class="form-editor-field__content" data-form-editor-field-content aria-label="<?= e($label) ?>">
		<?= $this->fetchContent('field') ?>
	</div>
</div>
