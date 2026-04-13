<?php

class LayoutComponentMainMenu extends AbstractLayoutComponent
{
	public const string ID = 'main_menu';

	public function buildTree(): array
	{
		$menuData = MainMenu::getMenuData($this->_webpage_composer);

		return $this->createComponentTree('mainMenu', [
			'menuData' => $menuData ?: [],
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
		$edit->url = Url::getSeoUrl(ResourceTypeWebpage::findWebpageIdWithWidget(WidgetList::MAINMENU)) ?? '';

		if (Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN)) {
			$return[] = $edit;
		}

		return $return;
	}
}
