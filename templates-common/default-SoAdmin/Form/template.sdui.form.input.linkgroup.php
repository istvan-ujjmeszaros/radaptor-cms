<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$values = is_array($this->props['values'] ?? null) ? $this->props['values'] : [];
?>
<span class="label"<?= (string)($this->props['label_style_attr'] ?? '') ?>><?= e((string)($this->props['label'] ?? '')) ?></span>
<?= $this->fetchContent('helper') ?>
<div class="form-link-group" role="list">
	<?php foreach ($values as $value): ?>
		<?php
		$url = (string)($value['url'] ?? '');
		$label = (string)($value['label'] ?? $url);
		$active = (bool)($value['active'] ?? false);
		?>
		<?php if ($active): ?>
			<span class="form-link-group-item active" role="listitem" aria-current="true"><?= e($label) ?></span>
		<?php else: ?>
			<a class="form-link-group-item" role="listitem" href="<?= e($url) ?>"><?= e($label) ?></a>
		<?php endif; ?>
	<?php endforeach; ?>
</div>
