<?php

class FormTypeThemeSelector extends AbstractForm
{
	public const string ID = 'theme_selector';

	public static function getName(): string
	{
		return t('form.' . self::ID . '.name');
	}

	public static function getDescription(): string
	{
		return t('form.' . self::ID . '.description');
	}

	public static function getListVisibility(): bool
	{
		return Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER);
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/components/theme-selector/',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	public function hasRole(): bool
	{
		return Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER);
	}

	public function commit(): void
	{
		if (Themes::saveSettings($this->savedata)) {
			SystemMessages::_ok(t('common.saved'));
		} else {
			SystemMessages::_notice(t('common.no_changes'));
		}
	}

	public function setMetadata(): void
	{
		$this->_meta->title = t('form.' . self::ID . '.title');
	}

	public function setInitValues(): void
	{
		$this->initvalues = Themes::getSettings();
	}

	public function makeInputs(): void
	{
		$layout_list = Layout::getLayoutListForSelect();

		foreach ($layout_list as $layout) {
			$themeselector = new FormInputSelect($layout['value'], $this);
			$themeselector->values = Themes::getThemeListForSelect();
			$themeselector->label = $layout['label'];
		}
	}
}
