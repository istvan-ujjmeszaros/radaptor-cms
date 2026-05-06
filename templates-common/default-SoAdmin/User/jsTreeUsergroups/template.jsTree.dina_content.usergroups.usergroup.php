<?php assert(isset($this) && $this instanceof Template); ?>

<h3><?= $this->props['data'][0]['title']; ?></h3>
<br/>

<div class="buttons">

	<?php if (Roles::hasRole(RoleList::ROLE_USERGROUPS_ADMIN)): ?>
		<button class="button right_img button_add_usergroup" type="button" onclick="widgettype._.location('<?= Form::getSeoUrl(FormList::USERGROUP, null, null, ['ref_id' => ($this->props['data'][0]['node_id'] | 0)]); ?>');"><?= Icons::get(IconNames::USERGROUP_ADD); ?>
			<?= e($this->strings['user.usergroup.new_child']) ?>
		</button>
		<br/>
		<br/>
		<button class="button right_img button_edit" type="button" onclick="widgettype._.location('<?= Form::getSeoUrl(FormList::USERGROUP, $this->props['data'][0]['node_id']); ?>');"><?= Icons::get(IconNames::EDIT); ?>
			<?= e($this->strings['common.edit']) ?>
		</button>
		<br/>
		<br/>
		<script type="text/javascript">
			var id_json = <?= json_encode($this->props['id']); ?>;
		</script>
		<button class="button right_img button_delete" type="button" onclick="widgettype.jstree.deleteRecursive('<?= $this->props['jstree_id']; ?>', id_json, '<?= Url::getAjaxUrl('Jstree.usergroupsAjaxDeleteRecursive'); ?>');"><?= Icons::get(IconNames::DELETE); ?>
			<?= e($this->strings['common.delete']) ?>
		</button>
		<br/>
		<br/>
	<?php endif; ?>

	<?php if (Roles::hasRole(RoleList::ROLE_USERGROUPS_ROLE_ADMIN)): ?>
		<button class="button right_img button_roles" type="button" onclick="widgettype._.location('<?= Url::getSeoUrl(ResourceTypeWebpage::findWebpageIdWithWidget(WidgetList::ROLESELECTOR)); ?>usergroup--<?= $this->props['data'][0]['node_id']; ?>');"><?= Icons::get(IconNames::ROLES); ?>
			<?= e($this->strings['user.usergroup.roles']) ?>
		</button>
		<br/>
		<br/>
	<?php endif; ?>

</div>

<hr>

<?= $this->fetchContent('help'); ?>
