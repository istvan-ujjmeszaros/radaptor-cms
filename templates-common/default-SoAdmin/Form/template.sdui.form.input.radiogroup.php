<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$values = is_array($this->props['values'] ?? null) ? $this->props['values'] : [];
$current_value = (string)($this->props['value'] ?? '');
?>
<span class="label"<?= (string)($this->props['label_style_attr'] ?? '') ?>><?= e((string)($this->props['label'] ?? '')) ?></span>
<?= $this->fetchSlot('helper') ?>
<?php $i = 0; ?>
<?php foreach ($values as $label => $value): ?>
	<?php $radio_id = (string)$this->props['id'] . '_' . ++$i; ?>
	<div>
		<input type="radio" name="<?= e((string)$this->props['name']) ?>" id="<?= e($radio_id) ?>" value="<?= e((string)$value) ?>"<?= (string)$value === $current_value ? ' checked' : '' ?>>
		<label for="<?= e($radio_id) ?>"><?= e((string)$label) ?></label>
	</div>
<?php endforeach; ?>
