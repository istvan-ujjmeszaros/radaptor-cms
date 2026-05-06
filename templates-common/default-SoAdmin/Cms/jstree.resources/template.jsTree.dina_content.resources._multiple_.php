<?php assert(isset($this) && $this instanceof Template); ?>
<h3><?= e($this->strings['selection.group']) ?></h3>

<div class="buttons">

	<script type="text/javascript">
		var id_json = <?= json_encode($this->props['id']); ?>;
	</script>
	<button class="button right_img button_delete" type="button" onclick="widgettype.jstree.deleteRecursive('<?= $this->props['jstree_id']; ?>', id_json, '<?= Url::getAjaxUrl('Jstree.resourcesAjaxDeleteRecursive'); ?>');"><?= Icons::get(IconNames::DELETE); ?>
		<?= e($this->strings['selection.delete_selected']) ?>
	</button>
	<br/>
	<br/>

</div>

<hr>
<br/>
<h4><?= e($this->strings['selection.selected_items']) ?>:</h4>

<ul>
	<?php foreach ($this->props['id'] as $id): ?>
		<?php $data = ResourceTreeHandler::getResourceTreeEntryDataById($id); ?>
		<?php if ($data): ?>
			<li><?= $data['title'] ?? $data['resource_name']; ?>
				<?php // echo MainMenu::getUrl($menu->node_id, false);?>
			</li>
		<?php endif; ?>
	<?php endforeach; ?>
</ul>


<hr>

<?= $this->fetchContent('help'); ?>
