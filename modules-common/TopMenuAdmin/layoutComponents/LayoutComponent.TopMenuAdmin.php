<?php

class LayoutComponentTopMenuAdmin extends AbstractLayoutComponent
{
	public const string ID = 'top_menu_admin';

	public function buildTree(): array
	{
		$admin_items = [[
			'url' => widget_url(WidgetList::USERLIST),
			'label' => t('user.list.title'),
		]];

		if (Roles::hasRole(RoleList::ROLE_USERGROUPS_ADMIN)) {
			$admin_items[] = [
				'url' => widget_url(WidgetList::USERGROUPLIST),
				'label' => t('admin.menu.usergroups'),
			];
		}

		if (Roles::hasRole(RoleList::ROLE_ROLES_ADMIN)) {
			$admin_items[] = [
				'url' => widget_url(WidgetList::ROLELIST),
				'label' => t('admin.menu.roles'),
			];
		}

		if (Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)) {
			$admin_items[] = [
				'url' => widget_url(WidgetList::ADMINMENU),
				'label' => t('admin.menu.admin_menu'),
			];
			$admin_items[] = [
				'url' => form_url(FormList::THEMESELECTOR, -1),
				'label' => t('admin.menu.theme_selector'),
			];
			$admin_items[] = [
				'url' => defined(WidgetList::class . '::PHPINFOFRAME')
					? widget_url((string) constant(WidgetList::class . '::PHPINFOFRAME'))
					: event_url('system.phpinfo'),
				'label' => t('admin.menu.phpinfo'),
			];
		}

		if (Roles::hasRole(RoleList::ROLE_SYSTEM_ADMINISTRATOR)) {
			$admin_items[] = [
				'url' => widget_url(WidgetList::RESOURCETREE),
				'label' => t('admin.menu.resource_tree'),
			];
			$admin_items[] = [
				'url' => widget_url(WidgetList::IMPORTEXPORT),
				'label' => t('admin.menu.import_export'),
			];
		}

		$current_url = Url::getCurrentUrlForReferer();
		$current_username = User::getCurrentUserUsername();
		$is_logged_in = $current_username !== null && $current_username !== '';

		return $this->createComponentTree('topMenuAdmin', [
			'current_username' => (string)$current_username,
			'is_logged_in' => $is_logged_in,
			'auth_url' => $is_logged_in
				? Url::modifyCurrentUrl([
					'context' => 'user',
					'event' => 'logout',
					'referer' => $current_url,
				])
				: Form::getSeoUrl(FormList::USERLOGIN, null, null, ['loginreferer' => $current_url]),
			'auth_label' => $is_logged_in ? t('common.logout') : t('user.login.title'),
			'administration_label' => t('admin.menu.section.administration'),
			'admin_items' => $admin_items,
		]);
	}

	public static function getLayoutComponentName(): string
	{
		return t('layout.' . self::ID . '.name');
	}

	public static function getLayoutComponentDescription(): string
	{
		return t('layout.' . self::ID . '.description');
	}
}
