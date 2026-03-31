<?php assert(isset($this) && $this instanceof Template); ?>
<div id="<?= e((string)($this->props['row_id'] ?? '')) ?>" class="input-row">
	<?= $this->fetchSlot('content') ?>
</div>
