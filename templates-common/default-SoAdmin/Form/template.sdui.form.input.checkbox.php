<?php assert(isset($this) && $this instanceof Template); ?>
<?php $checked = (bool)($this->props['checked'] ?? false); ?>
<label for="<?= e((string)$this->props['id']) ?>">
	<input id="<?= e((string)$this->props['id']) ?>" type="checkbox" name="<?= e((string)$this->props['name']) ?>" data-field-key="<?= e((string)($this->props['data_field_key'] ?? $this->props['name'] ?? '')) ?>" value="1"<?= $checked ? ' checked' : '' ?>>
	<?= e((string)($this->props['label'] ?? '')) ?>
</label>
<?= $this->fetchContent('helper') ?>
