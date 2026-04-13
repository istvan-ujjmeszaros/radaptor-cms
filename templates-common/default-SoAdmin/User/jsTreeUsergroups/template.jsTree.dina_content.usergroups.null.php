<?php assert(isset($this) && $this instanceof Template); ?>
<h3><?= e($this->strings['selection.invalid_entry']) ?>!</h3>
<br/>

<div class="buttons">

	<?php if (Roles::hasRole(RoleList::ROLE_USERGROUPS_ADMIN)): ?>
		<script type="text/javascript">
			var id_json = <?= json_encode($this->props['id']); ?>;
		</script>
		<button class="button right_img button_delete" type="button" onclick="widgettype.jstree.deleteRecursive('<?= $this->props['jstree_id']; ?>', id_json, '<?= Url::getAjaxUrl('Jstree.usergroupsAjaxDeleteRecursive'); ?>');"><?= Icons::get(IconNames::DELETE); ?>
			<?= e($this->strings['common.delete']) ?>
		</button>
		<br/>
		<br/>
	<?php endif; ?>

</div>
