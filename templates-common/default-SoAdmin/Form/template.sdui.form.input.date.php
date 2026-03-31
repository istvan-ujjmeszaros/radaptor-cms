<?php assert(isset($this) && $this instanceof Template); ?>
<?php $this->registerLibrary('CALENDAR'); ?>
<label for="<?= e((string)$this->props['id']) ?>"<?= (string)($this->props['label_style_attr'] ?? '') ?>><?= e((string)($this->props['label'] ?? '')) ?></label>
<?= $this->fetchSlot('helper') ?>
<input id="<?= e((string)$this->props['id']) ?>"<?= (string)($this->props['input_style_attr'] ?? '') ?> type="text" name="<?= e((string)$this->props['name']) ?>" value="<?= e((string)($this->props['value'] ?? '')) ?>" data-controller="flatpickr" data-flatpickr-date-format-value="Y-m-d">
