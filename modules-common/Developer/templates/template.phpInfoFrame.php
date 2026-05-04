<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$src = (string)($this->props['src'] ?? '');
?>
<div class="card shadow-sm">
	<div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
		<h1 class="h4 mb-0"><?= e($this->strings['widget.phpinfo_frame.title'] ?? 'PHPinfo') ?></h1>
		<a class="btn btn-outline-secondary btn-sm" href="<?= e($src) ?>" target="_blank" rel="noopener">
			<i class="bi bi-box-arrow-up-right me-1"></i><?= e($this->strings['widget.phpinfo_frame.open_raw'] ?? 'Open raw') ?>
		</a>
	</div>
	<div class="ratio" style="--bs-aspect-ratio: 72%;">
		<iframe
			src="<?= e($src) ?>"
			title="<?= e($this->strings['widget.phpinfo_frame.title'] ?? 'PHPinfo') ?>"
			class="border-0 bg-white"
			loading="lazy"
			referrerpolicy="same-origin"
		></iframe>
	</div>
</div>
