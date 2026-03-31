<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$values = is_array($this->props['values'] ?? null) ? $this->props['values'] : [];
$current_value = (string)($this->props['value'] ?? '');
$required = (bool)($this->props['required'] ?? true);
$placeholder_label = (string)($this->props['placeholder_label'] ?? '');
?>
<label for="<?= e((string)$this->props['id']) ?>"<?= (string)($this->props['label_style_attr'] ?? '') ?>><?= e((string)($this->props['label'] ?? '')) ?></label>
<?= $this->fetchSlot('helper') ?>
<select id="<?= e((string)$this->props['id']) ?>"<?= (string)($this->props['input_style_attr'] ?? '') ?> name="<?= e((string)$this->props['name']) ?>">
	<?php if ($required): ?>
		<option value=""><?= e($placeholder_label) ?></option>
	<?php endif; ?>
	<?php foreach ($values as $value): ?>
		<?php $value['inputtype'] ??= 'option'; ?>
		<?php if ($value['inputtype'] === 'option'): ?>
			<?php
			$option_value = (string)($value['value'] ?? '');
			$option_label = (string)($value['label'] ?? $placeholder_label);
			?>
			<option value="<?= e($option_value) ?>"<?= $option_value === $current_value ? ' selected' : '' ?>><?= e($option_label) ?></option>
		<?php elseif ($value['inputtype'] === 'optgroup_begin'): ?>
			<optgroup label="<?= e((string)($value['label'] ?? '')) ?>">
		<?php elseif ($value['inputtype'] === 'optgroup_end'): ?>
			</optgroup>
		<?php endif; ?>
	<?php endforeach; ?>
</select>
