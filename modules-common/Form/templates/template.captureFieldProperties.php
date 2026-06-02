<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$provider = new FormCaptureFieldPropertyProvider();
$mode = (string)($this->props['mode'] ?? FormCaptureFieldPropertyProvider::MODE_BUILDER);
$properties = is_array($this->props['properties'] ?? null) ? $this->props['properties'] : $provider->getProperties();
$field = is_array($this->props['field'] ?? null) ? $this->props['field'] : [];
$values = is_array($this->props['values'] ?? null) ? $this->props['values'] : $provider->valuesForField($field);
$strings = array_replace($provider->getStrings(), $this->strings);
$readOnly = (bool)($this->props['read_only'] ?? false);
$isBuilder = $mode === FormCaptureFieldPropertyProvider::MODE_BUILDER;
$isEditmode = $mode === FormCaptureFieldPropertyProvider::MODE_EDITMODE;

$labelFor = static function (array $property) use ($strings): string {
	$key = (string)($property['label_key'] ?? '');

	return (string)($strings[$key] ?? $key);
};

$translationLink = static function (array $property, string $extraClass = '') use ($strings): string {
	$target = (string)($property['i18n_target'] ?? '');

	if ($target === '') {
		return '';
	}

	$class = trim('form-builder__i18n-property-link ' . $extraClass);

	return '<a'
		. ' class="' . e($class) . '"'
		. ' data-form-builder-target="' . e($target) . '"'
		. ' data-action="click->form-builder#stopPropertyLinkClick"'
		. ' href="#"'
		. ' target="_blank"'
		. ' rel="noopener"'
		. ' aria-label="' . e((string)($strings['form.builder.action.open_translations'] ?? '')) . '"'
		. ' title="' . e((string)($strings['form.builder.action.open_translations'] ?? '')) . '"'
		. ' hidden'
		. '><i class="bi bi-translate" aria-hidden="true"></i></a>';
};

$renderBuilderControl = static function (array $property) use ($labelFor, $translationLink): void {
	$id = (string)($property['id'] ?? '');
	$control = (string)($property['control'] ?? 'text');
	$target = (string)($property['builder_target'] ?? '');
	$action = (string)($property['builder_action'] ?? '');
	$groupTarget = (string)($property['builder_group_target'] ?? '');
	$attrs = ' data-form-builder-target="' . e($target) . '" data-action="' . e($action) . '"';

	if ($control === 'checkbox') {
		?>
		<label class="form-check form-builder__checkbox">
			<input type="checkbox" class="form-check-input"<?= $attrs ?>>
			<span><?= e($labelFor($property)) ?></span>
			<?= $translationLink($property, 'ms-auto') ?>
		</label>
		<?php

		return;
	}

	$labelAttrs = 'class="form-label w-100' . ($id === 'options' ? ' mb-0' : '') . '"';

	if ($groupTarget !== '') {
		$labelAttrs .= ' data-form-builder-target="' . e($groupTarget) . '"';
	}
	?>
	<label <?= $labelAttrs ?>>
		<span class="form-builder__property-label">
			<span><?= e($labelFor($property)) ?></span>
			<?= $translationLink($property) ?>
		</span>
		<?php if ($control === 'textarea'): ?>
			<textarea rows="<?= e((string)($property['rows'] ?? 5)) ?>" class="form-control form-control-sm"<?= $attrs ?>></textarea>
		<?php else: ?>
			<input type="text" class="form-control form-control-sm"<?= $attrs ?>>
		<?php endif; ?>
	</label>
	<?php
};

$renderEditmodeControl = static function (array $property) use ($labelFor, $values, $readOnly, $provider, $field): void {
	$id = (string)($property['id'] ?? '');
	$control = (string)($property['control'] ?? 'text');
	$name = (string)($property['post_name'] ?? $id);
	$value = $values[$id] ?? '';
	$hidden = !$provider->propertyAppliesToField($property, $field);
	$disabled = $readOnly ? ' disabled' : '';

	if ($control === 'checkbox') {
		?>
		<label class="form-check form-editor-field-properties__checkbox"<?= $hidden ? ' hidden' : '' ?>>
			<input type="checkbox" class="form-check-input" name="<?= e($name) ?>" value="1"<?= !empty($value) ? ' checked' : '' ?><?= $disabled ?>>
			<span><?= e($labelFor($property)) ?></span>
		</label>
		<?php

		return;
	}
	?>
	<label class="form-label w-100"<?= $hidden ? ' hidden' : '' ?>>
		<span><?= e($labelFor($property)) ?></span>
		<?php if ($control === 'textarea'): ?>
			<textarea rows="<?= e((string)($property['rows'] ?? 5)) ?>" class="form-control form-control-sm" name="<?= e($name) ?>"<?= $disabled ?>><?= e((string)$value) ?></textarea>
		<?php else: ?>
			<input type="text" class="form-control form-control-sm" name="<?= e($name) ?>" value="<?= e((string)$value) ?>"<?= $disabled ?>>
		<?php endif; ?>
	</label>
	<?php
};
?>
<?php if ($isBuilder): ?>
	<div data-form-builder-target="fieldProperties" hidden>
		<?php foreach ($properties as $property): ?>
			<?php if (is_array($property)) {
				$renderBuilderControl($property);
			} ?>
		<?php endforeach; ?>
	</div>
<?php elseif ($isEditmode): ?>
	<?php
	library('__ADMIN_EDIT_MODE');
	$formId = (string)($this->props['form_id'] ?? '');
	$panelId = (string)($this->props['panel_id'] ?? $formId);
	$action = (string)($this->props['action'] ?? '');
	$target = is_array($this->props['target'] ?? null) ? $this->props['target'] : [];
	$title = (string)($strings['form.field_edit.title'] ?? '');
	$buttonLabel = (string)($strings['form.field_edit.action.save'] ?? '');
	$closeLabel = (string)($strings['form.field_edit.action.close'] ?? '');
	?>
	<form
		id="<?= e($panelId) ?>"
		class="form-editor-field-properties"
		method="post"
		action="<?= e($action) ?>"
		hx-post="<?= e($action) ?>"
		hx-swap="none"
		data-controller="form-timezone"
		data-form-editor-field-panel
		data-form-editor-field-uid="<?= e((string)($this->props['field_uid'] ?? '')) ?>"
		data-form-editor-field-key="<?= e((string)($field['key'] ?? $field['name'] ?? '')) ?>"
		hidden
	>
		<input type="hidden" name="<?= e(FormSubmitContext::FIELD_CSRF_TOKEN) ?>" value="<?= e((string)($this->props['csrf_token'] ?? '')) ?>">
		<?php foreach ($target as $name => $value): ?>
			<?php if (is_scalar($value) || $value === null): ?>
				<input type="hidden" name="<?= e((string)$name) ?>" value="<?= e((string)$value) ?>">
			<?php endif; ?>
		<?php endforeach; ?>
		<input type="hidden" name="field_uid" value="<?= e((string)($this->props['field_uid'] ?? '')) ?>">
		<input type="hidden" name="field_key" value="<?= e((string)($field['key'] ?? $field['name'] ?? '')) ?>">
		<input type="hidden" name="field_index" value="<?= e((string)($this->props['field_index'] ?? 0)) ?>">

		<div class="form-editor-field-properties__header">
			<h3><?= e($title) ?></h3>
			<button type="button" class="form-editor-field-properties__close" data-form-editor-field-close aria-label="<?= e($closeLabel) ?>">
				<?= Icons::get(IconNames::REMOVE, $closeLabel) ?>
			</button>
		</div>
		<div class="form-editor-field-properties__body">
			<?php foreach ($properties as $property): ?>
				<?php if (is_array($property)) {
					$renderEditmodeControl($property);
				} ?>
			<?php endforeach; ?>
		</div>
		<div class="form-editor-field-properties__actions">
			<button type="submit" class="btn btn-primary btn-sm">
				<?= Icons::get(IconNames::FORM_SAVE, $buttonLabel) ?>
				<?= e($buttonLabel) ?>
			</button>
			<button type="button" class="btn btn-outline-secondary btn-sm" data-form-editor-field-close>
				<?= e($closeLabel) ?>
			</button>
		</div>
	</form>
<?php endif; ?>
