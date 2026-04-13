<?php assert(isset($this) && $this instanceof Template); ?>

<h3><?= $this->props['data'][0]['title']; ?></h3>
<br/>

<div class="buttons">

	<?php if (Roles::hasRole(RoleList::ROLE_USERGROUPS_ROLE_ADMIN)): ?>
		<button class="button right_img button_roles" type="button" onclick="widgettype._.location('<?= Url::getSeoUrl(ResourceTypeWebpage::findWebpageIdWithWidget(WidgetList::ROLESELECTOR)); ?>usergroup--<?= $this->props['data'][0]['node_id']; ?>');"><?= Icons::get(IconNames::ROLES); ?>
			<?= e($this->strings['user.usergroup.roles']) ?>
		</button>
		<br/>
		<br/>
	<?php endif; ?>

</div>
<br/>

<?= e($this->strings['user.usergroup.system_managed_help']) ?>
<hr>

<?= $this->fetchSlot('help'); ?>
