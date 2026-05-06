<?php
assert(isset($this) && $this instanceof Template);
$this->registerLibrary('_ADMIN_DROPDOWN');
$layout_commands = is_array($this->props['layout_commands'] ?? null) ? $this->props['layout_commands'] : [];
?>

<div id="admin_dropdown_icon">
	<a href="/admin/" class="admin_dropdown_trigger"><?= Icons::get(IconNames::ADMIN_WRENCH); ?></a>
	<div class="admin_dropdown_menu">
		<ul>
			<li><a href="<?= e((string)($this->props['page_edit_url'] ?? '')) ?>"><?= e((string)($this->props['page_edit_label'] ?? '')) ?></a></li>
			<li><a href="<?= e((string)($this->props['edit_mode_url'] ?? '')) ?>"><?= e((string)($this->props['edit_mode_action_label'] ?? $this->props['edit_mode_label'] ?? '')) ?></a></li>

			<?php foreach ($layout_commands as $command): ?>
				<?php $icon = isset($command['icon']) ? IconNames::tryFrom((string)$command['icon']) : null; ?>
				<li><a href="<?= e((string)($command['url'] ?? '')) ?>"><?= Icons::get($icon); ?><?= e((string)($command['title'] ?? '')) ?></a></li>
			<?php endforeach; ?>

			<li><a href="<?= e((string)($this->props['home_url'] ?? '')) ?>"><?= e((string)($this->props['home_label'] ?? '')) ?></a></li>
			<li><a href="<?= e((string)($this->props['logout_url'] ?? '')) ?>"><?= e((string)($this->props['logout_label'] ?? '')) ?></a></li>
		</ul>
	</div>
</div>
<style>
	#admin_dropdown_icon { position: fixed; bottom: 10px; right: 10px; z-index: 9999; }
	.admin_dropdown_trigger { cursor: pointer; }
	.admin_dropdown_menu { display: none; position: absolute; bottom: 100%; right: 0; background: #fff; border: 1px solid #ccc; box-shadow: 0 2px 8px rgba(0,0,0,0.15); min-width: 200px; }
	.admin_dropdown_menu ul { list-style: none; margin: 0; padding: 5px 0; }
	.admin_dropdown_menu li a { display: block; padding: 5px 15px; color: #333; text-decoration: none; white-space: nowrap; }
	.admin_dropdown_menu li a:hover { background: #f0f0f0; }
	#admin_dropdown_icon:hover .admin_dropdown_menu { display: block; }
</style>
