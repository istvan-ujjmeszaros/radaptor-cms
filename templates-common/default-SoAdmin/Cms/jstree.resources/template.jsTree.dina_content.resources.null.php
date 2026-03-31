<?php assert(isset($this) && $this instanceof Template); ?>
<h3><?= e($this->strings['cms.resource_browser.invalid_entry']) ?>!</h3>

<div class="buttons">

	<script type="text/javascript">
		var id_json = <?= json_encode($this->props['id']); ?>;
	</script>
	<button class="button right_img button_delete" type="button" onclick="widgettype.jstree.deleteRecursive('<?= $this->props['jstree_id']; ?>', id_json, '<?= Url::getAjaxUrl('Jstree.resourcesAjaxDeleteRecursive'); ?>');"><?= Icons::get(IconNames::DELETE); ?>
		<?= e($this->strings['common.delete']) ?>
	</button>
	<br/>
	<br/>

</div>
