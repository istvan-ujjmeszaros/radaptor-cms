<?php assert(isset($this) && $this instanceof Template); ?>
<h3><?= e($this->strings['user.usergroup.all']) ?></h3>
<br/>

<div class="buttons">

	<?php if (Roles::hasRole(RoleList::ROLE_USERGROUPS_ADMIN)): ?>
		<button class="button right_img button_add_usergroup" type="button" onclick="widgettype._.location('<?= Form::getSeoUrl(FormList::USERGROUP, null, null, ['ref_id' => ($this->props['data'][0]['node_id'] | 0)]); ?>');"><?= Icons::get(IconNames::USERGROUP_ADD); ?>
			<?= e($this->strings['user.usergroup.new']) ?>
		</button>
		<br/>
		<br/>
	<?php endif; ?>

</div>

<hr>

<?= $this->fetchSlot('help'); ?>
