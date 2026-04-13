<?php assert(isset($this) && $this instanceof Template); ?>
<?php $this->registerLibrary('__ADMIN_USERLIST'); ?>

<script type="text/javascript">
	$(document).ready(function () {
		widgettype.userList.init({
			"ajaxBaseUrl": "<?= Url::getAjaxUrl("users.userListAjax"); ?>",
			"url_edit": "<?= Form::getSeoUrl(FormList::USER); ?>",
			"url_datasheet": "<?= Url::getSeoUrl(ResourceTypeWebpage::findWebpageIdWithWidget(WidgetList::USERDESCRIPTION)); ?>",
			"url_roles": "<?= Url::getSeoUrl(ResourceTypeWebpage::findWebpageIdWithWidget(WidgetList::ROLESELECTOR)); ?>user--",
			"url_usergroups": "<?= Url::getSeoUrl(ResourceTypeWebpage::findWebpageIdWithWidget(WidgetList::USERGROUPSELECTOR)); ?>user--",
			"userListTableId": "userlist-<?= $this->getWidgetConnection()->connection_id; ?>",
			"icon_edit": '<?= Icons::get(IconNames::EDIT, e($this->strings["user.action.edit"]), "medium") ?>',
			"icon_datasheet": '<?= Icons::get(IconNames::DATASHEET, e($this->strings["user.action.datasheet"]), "medium") ?>',
			"icon_roles": '<?= Icons::get(IconNames::ROLES, e($this->strings["user.action.roles"]), "medium") ?>',
			"icon_usergroups": '<?= Icons::get(IconNames::USERGROUP, e($this->strings["user.action.usergroups"]), "medium") ?>',
			"operation_edit": <?= Roles::hasRole(RoleList::ROLE_USERS_ADMIN) ? 'true' : 'false' ?>,
			"operation_datasheet": true,
			"operation_roles": <?= Roles::hasRole(RoleList::ROLE_USERS_ROLE_ADMIN) ? 'true' : 'false' ?>,
			"operation_usergroups": <?= Roles::hasRole(RoleList::ROLE_USERGROUPS_ADMIN) ? 'true' : 'false' ?>
		});
	});
</script>

<div class="subheader">
	<h1><?= e($this->strings['user.list.title']) ?></h1>
	<br class="cleaner">
</div>
<p>
	<a href="<?= form_url(FormList::USER); ?>" class="controller-menu"><?= e($this->strings['user.list.new']) ?></a><span></span>
</p>


<table class="display highlight_row commonDataTable" id="userlist-<?= $this->getWidgetConnection()->connection_id; ?>">
	<thead>
	<tr>
		<th width="1px"><?= e($this->strings['common.id']) ?></th>
		<th><?= e($this->strings['user.col.username']) ?></th>
		<th width="10px"><?= e($this->strings['user.col.actions']) ?></th>
	</tr>
	</thead>
</table>
