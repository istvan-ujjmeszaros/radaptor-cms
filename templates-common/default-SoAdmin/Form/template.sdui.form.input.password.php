<?php assert(isset($this) && $this instanceof Template); ?>
<label for="<?= e((string)$this->props['id']) ?>"<?= (string)($this->props['label_style_attr'] ?? '') ?>><?= e((string)($this->props['label'] ?? '')) ?></label>
<?= $this->fetchSlot('helper') ?>
<input id="<?= e((string)$this->props['id']) ?>"<?= (string)($this->props['input_style_attr'] ?? '') ?> type="password" name="<?= e((string)$this->props['name']) ?>" value="">
