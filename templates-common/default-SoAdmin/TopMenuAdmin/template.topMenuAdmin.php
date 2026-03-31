<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$current_username = (string)($this->props['current_username'] ?? '');
$auth_url = (string)($this->props['auth_url'] ?? '');
$auth_label = (string)($this->props['auth_label'] ?? '');
$administration_label = (string)($this->props['administration_label'] ?? '');
$admin_items = is_array($this->props['admin_items'] ?? null) ? $this->props['admin_items'] : [];
?>
<div id="topmenu-right">
	<?php if ($current_username !== ''): ?>
		<b><?= e($current_username); ?></b><span class="separator">|</span>
		<a href="<?= e($auth_url) ?>"><?= e($auth_label) ?></a>
	<?php else: ?>
		<span class="separator">|</span>
		<a href="<?= e($auth_url) ?>"><?= e($auth_label) ?></a>
	<?php endif; ?>
</div>
<div id="topmenu-left">
	<div class="topmenu-element">
		<a><?= e($administration_label) ?><span class="gbma"></span></a>
		<div class="menu-container">
			<ul>
				<?php foreach ($admin_items as $admin_item): ?>
					<li>
						<a href="<?= e((string)($admin_item['url'] ?? '')) ?>"><span><?= e((string)($admin_item['label'] ?? '')) ?></span></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div><!--span class="separator">|</span-->
</div>
<style>
	.topmenu-element .menu-container {
		display: none;
		transform: translateY(-10px);
		opacity: 0;
		transition: opacity 0.15s ease, transform 0.15s ease;
	}
	.topmenu-element:hover .menu-container {
		display: block;
		transform: translateY(0);
		opacity: 1;
	}
</style>
