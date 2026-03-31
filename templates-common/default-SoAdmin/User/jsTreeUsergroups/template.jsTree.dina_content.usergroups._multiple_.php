<?php assert(isset($this) && $this instanceof Template); ?>
<h3><?= e($this->strings['selection.group']) ?></h3>
<br/>

<div class="buttons">

	<?php if (Roles::hasRole(RoleList::ROLE_USERGROUPS_ADMIN)): ?>
		<script type="text/javascript">
			var id_json = <?= json_encode($this->props['id']); ?>;
		</script>
		<button class="button right_img button_delete" type="button" onclick="widgettype.jstree.deleteRecursive('<?= $this->props['jstree_id']; ?>', id_json, '<?= Url::getAjaxUrl('Jstree.usergroupsAjaxDeleteRecursive'); ?>');"><?= Icons::get(IconNames::DELETE); ?>
			<?= e($this->strings['selection.delete_selected']) ?>
		</button>
		<br/>
		<br/>
	<?php endif; ?>

</div>

<hr>
<br/>
<h4><?= e($this->strings['selection.selected_items']) ?>:</h4>

<ul>
	<?php foreach ($this->props['selected_items'] as $item): ?>
		<li><?= $item['title']; ?></li>
	<?php endforeach; ?>
</ul>


<hr>

<?= $this->fetchSlot('help'); ?>
