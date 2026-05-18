<?php assert(isset($this) && $this instanceof Template); ?>
<label for="<?= e((string)$this->props['id']) ?>"<?= (string)($this->props['label_style_attr'] ?? '') ?>><?= e((string)($this->props['label'] ?? '')) ?></label>
<?= $this->fetchContent('helper') ?>
<textarea id="<?= e((string)$this->props['id']) ?>"<?= (string)($this->props['input_style_attr'] ?? '') ?> name="<?= e((string)$this->props['name']) ?>" data-field-key="<?= e((string)($this->props['data_field_key'] ?? $this->props['name'] ?? '')) ?>"><?= e((string)($this->props['value'] ?? '')) ?></textarea>
