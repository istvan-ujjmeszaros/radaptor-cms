<?php

class LayoutComponentAdminMenu extends AbstractLayoutComponent
{
	public const string ID = 'admin_menu';

	public function buildTree(): array
	{
		$menuData = AdminMenu::getMenuData($this->_webpage_composer);

		return $this->createComponentTree('adminMenu', [
			'menuData' => $menuData ?: [],
			'custom_menu_label' => t('admin.menu.custom_menu'),
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

	public function getEditableCommands(): array
	{
		$return = [];

		$edit = new WidgetEditCommand();

		// Már hozzá van rendelve tartalom, azt szerkesztjük
		$edit->title = t('layout.' . self::ID . '.name');
		//$edit->icon = 'edit';
		$edit->url = Url::getSeoUrl(ResourceTypeWebpage::findWebpageIdWithWidget(WidgetList::ADMINMENU));

		if (Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)) {
			$return[] = $edit;
		}

		return $return;
	}
}
