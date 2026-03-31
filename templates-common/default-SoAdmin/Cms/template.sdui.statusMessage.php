<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$this->registerLibrary('_SDUI_STATUSMESSAGE');
$severity = (string)($this->props['severity'] ?? 'info');
$title = (string)($this->props['title'] ?? '');
$message = (string)($this->props['message'] ?? '');
$missing = is_array($this->props['missing'] ?? null) ? $this->props['missing'] : [];
$redirect_url = (string)($this->props['redirect_url'] ?? '');
?>
<div class="sdui-status sdui-status-<?= e($severity) ?>">
	<?php if ($title !== ''): ?>
		<strong class="sdui-status-title"><?= e($title) ?></strong>
	<?php endif; ?>
	<?php if ($message !== ''): ?>
		<div class="sdui-status-message"><?= e($message) ?></div>
	<?php endif; ?>
	<?php if ($missing !== []): ?>
		<ul class="sdui-status-list">
			<?php foreach ($missing as $param => $description): ?>
				<li><code><?= e((string)$param) ?></code> - <?= e((string)$description) ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php if ($redirect_url !== ''): ?>
		<p class="sdui-status-link"><a href="<?= e($redirect_url) ?>"><?= e($redirect_url) ?></a></p>
	<?php endif; ?>
</div>
