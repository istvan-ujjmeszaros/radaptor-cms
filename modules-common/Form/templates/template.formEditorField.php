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
$commands = is_array($this->props['commands'] ?? null) ? $this->props['commands'] : [];
$editModeHxSwap = 'none show:none focus-scroll:false';

if ($commands === []) {
	$commands[] = [
		'id' => 'edit',
		'title' => $title,
		'icon' => IconNames::EDIT->value,
		'action' => FormEditorFieldCommand::ACTION_PANEL,
		'panel_id' => $panelId,
	];
}
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
		<?php foreach ($commands as $command): ?>
			<?php if (!is_array($command)) {
				continue;
			} ?>
			<?php
			$commandId = trim((string)($command['id'] ?? ''));
			$commandAction = (string)($command['action'] ?? '');
			$commandTitle = (string)($command['title'] ?? '');
			$commandIcon = IconNames::tryFrom((string)($command['icon'] ?? ''));
			$commandVariant = (string)($command['variant'] ?? FormEditorFieldCommand::VARIANT_DEFAULT);
			$commandClassSuffix = preg_replace('/[^a-z0-9_-]+/i', '-', $commandId !== '' ? $commandId : 'command');
			$commandClasses = 'form-editor-field__command form-editor-field__command--' . $commandClassSuffix;

			if ($commandVariant === FormEditorFieldCommand::VARIANT_DANGER) {
				$commandClasses .= ' form-editor-field__command--danger';
			}

			if ($commandAction === FormEditorFieldCommand::ACTION_PANEL) {
				$commandClasses .= ' form-editor-field__edit-button';
			}

			$disabled = !empty($command['disabled']);
			$payload = is_array($command['payload'] ?? null) ? $command['payload'] : [];
			$hxValues = $payload !== [] ? json_encode($payload, JSON_THROW_ON_ERROR) : '';
			$method = strtolower((string)($command['method'] ?? 'post'));
			$hxAttribute = $method === 'get' ? 'hx-get' : 'hx-post';
			$confirmMessage = trim((string)($command['confirm_message'] ?? ''));
			$confirmTitle = trim((string)($command['confirm_title'] ?? ''));
			$confirmLabel = trim((string)($command['confirm_label'] ?? ''));
			$cancelLabel = trim((string)($command['cancel_label'] ?? ''));
			?>
			<button
				type="button"
				class="<?= e($commandClasses) ?>"
				<?php if ($commandAction === FormEditorFieldCommand::ACTION_PANEL): ?>
					data-form-editor-field-toggle
					aria-controls="<?= e((string)($command['panel_id'] ?? $panelId)) ?>"
					aria-expanded="false"
				<?php elseif ($commandAction === FormEditorFieldCommand::ACTION_HTMX && !$disabled): ?>
					<?= $hxAttribute ?>="<?= e((string)($command['url'] ?? '')) ?>"
					hx-swap="<?= e($editModeHxSwap) ?>"
					<?= $hxValues !== '' ? 'hx-vals="' . e($hxValues) . '"' : '' ?>
					data-edit-mode-command
					<?php if ($confirmMessage !== ''): ?>
						hx-confirm="<?= e($confirmMessage) ?>"
						data-edit-mode-confirm
						data-edit-mode-confirm-title="<?= e($confirmTitle) ?>"
						data-edit-mode-confirm-message="<?= e($confirmMessage) ?>"
						data-edit-mode-confirm-label="<?= e($confirmLabel) ?>"
						data-edit-mode-cancel-label="<?= e($cancelLabel) ?>"
					<?php endif; ?>
				<?php endif; ?>
				<?= $disabled ? 'disabled aria-disabled="true"' : '' ?>
				aria-label="<?= e($commandTitle) ?>"
				title="<?= e($commandTitle) ?>"
			>
				<?= Icons::get($commandIcon, $commandTitle) ?>
			</button>
		<?php endforeach; ?>
	</div>
	<div class="form-editor-field__content" data-form-editor-field-content aria-label="<?= e($label) ?>">
		<?= $this->fetchContent('field') ?>
	</div>
</div>
