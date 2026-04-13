<?php assert(isset($this) && $this instanceof Template); ?>
<?php $this->setDebugType(TemplateDebugType::DEBUG_HTML); ?>
<?php $this->registerLibrary('__ADMIN_RESOURCE_ACL_SELECTOR'); ?>

<script type="text/javascript">
	$(document).ready(function () {
		widgettype.resourceAclSelector.init({
			"ajaxBaseUrl": "<?= Url::getAjaxUrl("resource.AclSelectorAjax", ["for_id" => $this->props['selectorId']]); ?>",
			"checkbox_id": 'checkbox_inherit',
			"inheritedTableId": "acl-userselector-inherited-<?= $this->getWidgetConnection()->connection_id; ?>",
			"specificTableId": "acl-userselector-specific-<?= $this->getWidgetConnection()->connection_id; ?>",
			"inheritedAjax": "<?= Url::getSeoUrl(ResourceTypeWebpage::findWebpageIdWithWidget(WidgetList::USERDESCRIPTION)); ?>",
			"icon_user": '<?= Icons::get(IconNames::USER) ?>',
			"icon_usergroup": '<?= Icons::get(IconNames::USERGROUP) ?>',
			"icon_trash": '<?= Icons::get(IconNames::TRASH, $this->strings["common.delete"]) ?>'
		});
	});
</script>

<h2><?= e($this->strings['cms.resource_acl.title']) ?></h2>
<h3><i>[<?= $this->props['title']; ?>]</i></h3>


<input type="checkbox" name="inherit" id="checkbox_inherit"<?php if ($this->props['is_inheriting_acl']): ?> checked="checked"<?php endif; ?>>
<label style="margin-left:10px;width:auto;" for="checkbox_inherit"><?= e($this->strings['cms.resource_acl.inherit_label']) ?></label> <br/>

<div id="acl-userselector-inherited">
	<h1><?= e($this->strings['cms.resource_acl.inherited_title']) ?></h1>
	<table class="display highlight_row commonDataTable" id="acl-userselector-inherited-<?= $this->getWidgetConnection()->connection_id; ?>">
		<thead>
		<tr>
			<th style="text-align:left;"><?= e($this->strings['cms.resource_acl.subject']) ?></th>
			<th width="10px"><?= e($this->strings['cms.resource_acl.permission.list']) ?></th>
			<th width="10px"><?= e($this->strings['cms.resource_acl.permission.view']) ?></th>
			<th width="10px"><?= e($this->strings['cms.resource_acl.permission.create']) ?></th>
			<th width="10px"><?= e($this->strings['cms.resource_acl.permission.edit']) ?></th>
			<th width="10px"><?= e($this->strings['cms.resource_acl.permission.delete']) ?></th>
		</tr>
		</thead>
	</table>
</div>
<div style="clear:both;"></div>
<div id="acl-userselector-specific">
	<h1><?= e($this->strings['cms.resource_acl.specific_title']) ?></h1>
	<table class="display highlight_row commonDataTable" id="acl-userselector-specific-<?= $this->getWidgetConnection()->connection_id; ?>">
		<thead width="100%">
		<tr>
			<th style="text-align:left;"><?= e($this->strings['cms.resource_acl.subject']) ?></th>
			<th width="10px"><?= e($this->strings['cms.resource_acl.permission.list']) ?></th>
			<th width="10px"><?= e($this->strings['cms.resource_acl.permission.view']) ?></th>
			<th width="10px"><?= e($this->strings['cms.resource_acl.permission.create']) ?></th>
			<th width="10px"><?= e($this->strings['cms.resource_acl.permission.edit']) ?></th>
			<th width="10px"><?= e($this->strings['cms.resource_acl.permission.delete']) ?></th>
			<th width="10px">&nbsp;</th>
		</tr>
		</thead>
	</table>
</div>
