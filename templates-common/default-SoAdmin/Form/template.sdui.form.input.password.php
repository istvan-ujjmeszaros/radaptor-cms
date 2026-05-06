<?php assert(isset($this) && $this instanceof Template); ?>
<label for="<?= e((string)$this->props['id']) ?>"<?= (string)($this->props['label_style_attr'] ?? '') ?>><?= e((string)($this->props['label'] ?? '')) ?></label>
<?= $this->fetchContent('helper') ?>
<input id="<?= e((string)$this->props['id']) ?>"<?= (string)($this->props['input_style_attr'] ?? '') ?> type="password" name="<?= e((string)$this->props['name']) ?>" value="">
