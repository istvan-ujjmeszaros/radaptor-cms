<?php assert(isset($this) && $this instanceof Template); ?>
<div class="headermenu">
	<ul>
		<?php foreach ($this->props['menuData'] as $menu): ?>
			<li<?php if ($menu['is_active']): ?> class="active"<?php endif; ?>>
				<a href="<?= $menu['href']; ?>"><?= $menu['node_name']; ?></a></li>
		<?php endforeach; ?>
	</ul>
</div>
