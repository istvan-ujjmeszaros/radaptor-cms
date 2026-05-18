<?php assert(isset($this) && $this instanceof Template); ?>
<input id="<?= e((string)$this->props['id']) ?>" type="hidden" name="<?= e((string)$this->props['name']) ?>" data-field-key="<?= e((string)($this->props['data_field_key'] ?? $this->props['name'] ?? '')) ?>" value="<?= e((string)($this->props['value'] ?? '')) ?>">
