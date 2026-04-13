<?php assert(isset($this) && $this instanceof Template); ?>
<div id="admin_panel">
	<div id="admin_panel_content">
		<?php if (ResourceTreeHandler::canAccessResource($this->getPageId(), ResourceAcl::_ACL_EDIT)): ?>
			<button class="button right_img button_edit" type="button" onclick="window.location = '<?= Form::getSeoUrl(FormList::WEBPAGEPAGE, $this->getPageId()); ?>';">
				<?= Icons::get(IconNames::EDIT); ?> <?= e((string)($this->strings['form.webpage_page.name'] ?? '')) ?>
			</button>
		<?php endif; ?>
		<?php
		foreach ($this->props['layoutComponents'] as $layoutComponent) {
			if (!$layoutComponent instanceof iLayoutComponent) {
				continue;
			}

			foreach ($layoutComponent->getEditableCommands() as $command) {
				?>
				<button class="button right_img button_edit" type="button" onclick="window.location = '<?= $command->url; ?>';"><?= Icons::get($command->icon); ?><?= $command->title; ?></button>
			<?php
			}
		}
?>
	</div>
</div>
<div id="admin_panel_switcher">
	<span><img src="<?= Config::PATH_CDN->value(); ?>admin-site/admin-switcher-down.png" alt="<?= e((string)($this->strings['admin.menu.section.administration'] ?? '')) ?>" title=""></span>
</div>
