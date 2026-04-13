<?php assert(isset($this) && $this instanceof Template); ?>
<?php
$adminmenu = $this->props['data'][0] ?? false;

if (!is_array($adminmenu)) {
	return true;
}
?>

<h3><?= $adminmenu['node_name']; ?></h3>
<br/>

<div class="buttons">

	<button <?php if (!$adminmenu['has_link']): ?>disabled <?php endif; ?>class="admin_button button_preview" type="button" onclick="widgettype._.openWindow('<?= $adminmenu['url']; ?>');"><?= Icons::get(IconNames::LOOK); ?>
		<?= e($this->strings['common.preview']) ?>
	</button>
	<br/>
	<br/>
	<button class="button right_img button_edit" type="button" onclick="widgettype._.location('<?= Form::getSeoUrl(FormList::ADMINMENUMENUELEMENT, $adminmenu['node_id']); ?>');"><?= Icons::get(IconNames::EDIT); ?>
		<?= e($this->strings['common.edit']) ?>
	</button>
	<br/>
	<br/>
	<script type="text/javascript">
		var id_json = <?= json_encode($this->props['id']); ?>;
	</script>
	<button class="button right_img button_delete" type="button" onclick="widgettype.jstree.deleteRecursive('<?= $this->props['jstree_id']; ?>', id_json, '<?= Url::getAjaxUrl('Jstree.adminMenuAjaxDeleteRecursive'); ?>');"><?= Icons::get(IconNames::DELETE); ?>
		<?= e($this->strings['common.delete']) ?>
	</button>
	<br/>
	<br/>

</div>

<hr>
<br/>

<?php if ($adminmenu['is_external']): ?>
	<h4><?= e($this->strings['cms.menu.external_link']) ?></h4>
	<small><?= $adminmenu['url']; ?></small>
<?php elseif (is_null($adminmenu['page_id'])): ?>
	<h4 style="color:red;"><?= e($this->strings['cms.menu.no_link_configured']) ?></h4>
<?php else: ?>
	<h4><?= e($this->strings['cms.menu.internal_link']) ?></h4>
	<small><?= $adminmenu['internal_url']; ?></small>
<?php endif; ?>
