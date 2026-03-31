<?php assert(isset($this) && $this instanceof Template); ?>
<h3><?= $this->props['data'][0]['title'] == '' ? $this->props['data'][0]['resource_name'] : $this->props['data'][0]['title']; ?></h3>

<div class="buttons">

	<?php if ($this->props['indexpage_node_id'] === false): ?>
		<?php if (ResourceAcl::canAccessResource($this->props['data'][0]['node_id'], ResourceAcl::_ACL_CREATE)): ?>
			<button class="button right_img" type="button" onclick="widgettype._.location('<?= Form::getSeoUrl(FormList::WEBPAGEPAGE, null, null, [
				'formdata-resource_name' => 'index',
				'ref_id' => ($this->props['data'][0]['node_id'] | 0),
			]); ?>', 'form_window');"><?= Icons::get(IconNames::WARNING); ?>
				<?= e($this->strings['cms.resource_browser.create_index_page']) ?>
			</button>
		<?php endif; ?>
	<?php elseif ($this->props['indexpage_data'] !== null): ?>
		<?php if (ResourceAcl::canAccessResource($this->props['data'][0]['node_id'], ResourceAcl::_ACL_VIEW)): ?>
		<button class="button right_img button_preview" type="button" onclick="widgettype._.openWindow('<?= Url::getUrl('resource.view', [
			'folder' => $this->props['indexpage_data']['path'],
			'resource' => $this->props['indexpage_data']['resource_name'],
			'domain_context' => ResourceTypeRoot::getDomainContextForResource($this->props['data'][0]['node_id']),
		]); ?>');"><?= Icons::get(IconNames::LOOK); ?>
			<?= e($this->strings['cms.resource_browser.preview_index_page']) ?>
		</button>
			<br/>
			<br/>
		<?php endif; ?>
	<?php endif; ?>
	<?php if (ResourceAcl::canAccessResource($this->props['data'][0]['node_id'], ResourceAcl::_ACL_EDIT)): ?>
		<button class="button right_img button_edit" type="button" onclick="widgettype._.location('<?= Form::getSeoUrl(FormList::WEBPAGEFOLDER, $this->props['data'][0]['node_id']); ?>');"><?= Icons::get(IconNames::EDIT); ?>
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
	<?php if (ResourceAcl::canAccessResource($this->props['data'][0]['node_id'], ResourceAcl::_ACL_CREATE)): ?>
		<button class="button right_img button_add_folder" type="button" onclick="widgettype._.location('<?= Form::getSeoUrl(FormList::WEBPAGEFOLDER, null, null, ['ref_id' => ($this->props['data'][0]['node_id'] ?? 0)]); ?>');"><?= Icons::get(IconNames::FOLDER_ADD); ?>
			<?= e($this->strings['resource.new_folder']) ?>
		</button>
		<br/>
		<br/>
		<button class="button right_img button_add_webpage" type="button" onclick="widgettype._.location('<?= Form::getSeoUrl(FormList::WEBPAGEPAGE, null, null, ['ref_id' => ($this->props['data'][0]['node_id'] ?? 0)]); ?>');"><?= Icons::get(IconNames::WEBPAGE_ADD); ?>
			<?= e($this->strings['resource.new_webpage']) ?>
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

<?= $this->fetchSlot('help'); ?>
