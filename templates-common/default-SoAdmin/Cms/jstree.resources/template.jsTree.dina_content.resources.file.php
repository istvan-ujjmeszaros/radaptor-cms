<?php assert(isset($this) && $this instanceof Template); ?>

<h3><?= $this->props['data'][0]['title'] == '' ? $this->props['data'][0]['resource_name'] : $this->props['data'][0]['title']; ?></h3>

<?php if (ResourceTypeFile::isImage($this->props['data'][0]['node_id'])): ?>
	<div class="thumbnail-holder">
		<img src="<?= Url::getUrl('resource.view', [
			'folder' => $this->props['data'][0]['path'],
			'resource' => PredefinedImageHandler::rewriteFileName($this->props['data'][0]['resource_name'], 'admin_thumbnail'),
			'domain_context' => ResourceTypeRoot::getDomainContextForResource($this->props['data'][0]['node_id']),
		]); ?>" alt="<?= $this->props['data'][0]['resource_name']; ?>" title="<?= $this->props['data'][0]['resource_name']; ?>">
	</div>
<?php endif; ?>

<div class="buttons">

	<?= $this->fetchContent('insert_button'); ?>

	<?php if (ResourceAcl::canAccessResource($this->props['data'][0]['node_id'], ResourceAcl::_ACL_VIEW)): ?>
		<button class="button right_img button_preview" type="button" onclick="widgettype._.openWindow('<?= Url::getUrl('resource.view', [
			'folder' => $this->props['data'][0]['path'],
			'resource' => $this->props['data'][0]['resource_name'],
			'domain_context' => ResourceTypeRoot::getDomainContextForResource($this->props['data'][0]['node_id']),
		]); ?>');"><?= Icons::get(IconNames::LOOK); ?>
			<?= e($this->strings['common.preview']) ?>
		</button>
		<br/>
		<br/>
		<button class="button right_img button_preview" type="button" onclick="window.location='<?= Url::getUrl('resource.view', [
			'folder' => $this->props['data'][0]['path'],
			'resource' => $this->props['data'][0]['resource_name'],
			'download' => 1,
		]); ?>'"><?= Icons::get(IconNames::DOWNLOAD); ?>
			<?= e($this->strings['common.download']) ?>
		</button>
		<br/>
		<br/>
	<?php endif; ?>
	<?php if (ResourceAcl::canAccessResource($this->props['data'][0]['node_id'], ResourceAcl::_ACL_EDIT)): ?>
		<button class="button right_img button_edit" type="button" onclick="widgettype._.location('<?= Form::getSeoUrl(FormList::WEBPAGEFILE, $this->props['data'][0]['node_id']); ?>');"><?= Icons::get(IconNames::EDIT); ?>
			<?= e($this->strings['resource.properties']) ?>
		</button>
		<br/>
		<br/>
	<?php endif; ?>
	<?php if (ResourceAcl::canAccessResource($this->props['data'][0]['node_id'], ResourceAcl::_ACL_DELETE)): ?>
		<script type="text/javascript">
			var id_json = <?= json_encode($this->props['id']); ?>;
		</script>
		<button class="button right_img button_delete" type="button" onclick="widgettype.jstree.deleteRecursive('<?= $this->props['jstree_id']; ?>', id_json, '<?= Url::getAjaxUrl('Jstree.resourcesAjaxDeleteRecursive'); ?>');"><?= Icons::get(IconNames::DELETE); ?>
			<?= e($this->strings['common.delete']) ?>
		</button>
		<br/>
		<br/>
	<?php endif; ?>
	<?php if (ResourceAcl::canAccessResource(ResourceTreeHandler::getFolderId($this->props['data'][0]['node_id']), ResourceAcl::_ACL_CREATE)): ?>
		<button class="button right_img button_file_upload" type="button" onclick="widgettype._.location('<?= Url::getSeoUrl(ResourceTypeWebpage::findWebpageIdWithWidget(WidgetList::FILEUPLOAD)); ?>?ref_id=<?= ResourceTreeHandler::getFolderId($this->props['data'][0]['node_id']); ?>');"><?= Icons::get(IconNames::UPLOAD); ?>
			<?= e($this->strings['resource.upload_file']) ?>
		</button>
		<br/>
		<br/>
	<?php endif; ?>
	<?php if (Roles::hasRole(RoleList::ROLE_ACL_VIEWER) || Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)): ?>
		<button class="button right_img button_security" type="button" onclick="widgettype._.location('<?= Url::getSeoUrl(ResourceTypeWebpage::findWebpageIdWithWidget(WidgetList::RESOURCEACLSELECTOR)); ?>resource--<?= $this->props['data'][0]['node_id']; ?>');"><?= Icons::get(IconNames::LOCK); ?>
			<?= e($this->strings['resource.security']) ?>
		</button>
		<br/>
		<br/>
	<?php endif; ?>

</div>

<hr>

<?= $this->fetchContent('help'); ?>
