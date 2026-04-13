<?php assert(isset($this) && $this instanceof Template); ?>
<div style="background-color:yellow">
	<?= e($this->strings['cms.library.unknown']) ?>: <?= htmlspecialchars((string)($this->props['library_name'] ?? '')); ?>
</div>
