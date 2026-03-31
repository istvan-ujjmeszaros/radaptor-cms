<?php assert(isset($this) && $this instanceof Template); ?>

<h3><?= $this->props['data'][0]['title']; ?></h3>
<br/>

<div class="buttons">

	<button class="button right_img button_add_role" type="button" onclick="widgettype._.location('<?= Form::getSeoUrl(FormList::ROLE, null, null, ['ref_id' => ($this->props['data'][0]['node_id'] | 0)]); ?>');"><?= Icons::get(IconNames::ADD); ?>
		<?= e($this->strings['user.role.new_child']) ?>
	</button>
	<br/>
	<br/>
	<button class="button right_img button_edit" type="button" onclick="widgettype._.location('<?= Form::getSeoUrl(FormList::ROLE, $this->props['data'][0]['node_id']); ?>');"><?= Icons::get(IconNames::EDIT); ?>
		<?= e($this->strings['common.edit']) ?>
	</button>
	<br/>
	<br/>
	<script type="text/javascript">
		var id_json = <?= json_encode($this->props['id']); ?>;
	</script>
	<button class="button right_img button_delete" type="button" onclick="widgettype.jstree.deleteRecursive('<?= $this->props['jstree_id']; ?>', id_json, '<?= Url::getAjaxUrl('Jstree.rolesAjaxDeleteRecursive'); ?>');"><?= Icons::get(IconNames::DELETE); ?>
		<?= e($this->strings['common.delete']) ?>
	</button>
	<br/>
	<br/>

</div>

<hr>

<?= $this->fetchSlot('help'); ?>
