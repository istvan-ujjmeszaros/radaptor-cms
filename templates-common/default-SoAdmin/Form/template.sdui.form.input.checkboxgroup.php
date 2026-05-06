<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$values = is_array($this->props['values'] ?? null) ? $this->props['values'] : [];
$current_values = is_array($this->props['value'] ?? null) ? $this->props['value'] : [];
?>
<span class="label"<?= (string)($this->props['label_style_attr'] ?? '') ?>><?= e((string)($this->props['label'] ?? '')) ?></span>
<?= $this->fetchContent('helper') ?>
<?php $i = 0; ?>
<?php foreach ($values as $key => $label): ?>
	<?php $checkbox_id = (string)$this->props['id'] . '_' . ++$i; ?>
	<div>
		<input type="checkbox" class="checkbox" name="<?= e((string)$this->props['name']) ?>[<?= e((string)$key) ?>]" id="<?= e($checkbox_id) ?>" value="1"<?= !empty($current_values[$key]) ? ' checked' : '' ?>>
		<label class="innerLabel" for="<?= e($checkbox_id) ?>"><?= e((string)$label) ?></label>
	</div>
<?php endforeach; ?>
