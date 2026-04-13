<?php assert(isset($this) && $this instanceof Template); ?>
<?php if ($this->props['insertUrl'] !== false): ?>
	<button class="button" type="button" onclick="window.SetUrl('<?= $this->props['insertUrl']; ?>',0,0,'');">
		<span><?= e($this->strings['cms.resource_browser.insert']) ?></span></button>
	<br/>
	<br/>
<?php endif; ?>
