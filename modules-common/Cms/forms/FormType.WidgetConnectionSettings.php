<?php

class FormTypeWidgetConnectionSettings extends AbstractForm
{
	public const string ID = 'widget_connection_settings';

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
			'path' => '/admin/components/widget-connection-settings/edit/',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	public static function getRequiredUrlParams(): array
	{
		return [
			'item_id' => t('cms.widget_connection_settings.missing_widget_id'),
		];
	}

	public function hasRole(): bool
	{
		return Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN);
	}

	public function commit(): void
	{
		if (WidgetSettings::saveSettings($this->savedata, $this->getItemId())) {
			SystemMessages::addSystemMessage(t('common.saved'));
		} else {
			SystemMessages::addSystemMessage(t('common.no_changes'));
		}
	}

	public function setMetadata(): void
	{
		$this->_meta->title = t('form.' . self::ID . '.title');

		if ($this->getItemId()) {
			$this->_meta->sub_title = t('cms.widget_connection_settings.subtitle', [
				'page' => ResourceTreeHandler::getResourceTreeEntryName($this->getItemId()),
			]);
		}
	}

	public function setInitValues(): void
	{
		$this->initvalues = WidgetSettings::getSettings($this->getItemId());
	}

	public function makeInputs(): void
	{
		$widget_width = new FormInputSelect('widget_width', $this);
		$widget_width->label = t('cms.widget_connection_settings.field.widget_width.label');
		$widget_width->values = Themes::getWidthValuesForSelect($this->getItemId());

		$is_last = new FormInputCheckbox('is_last', $this);
		$is_last->label = t('cms.widget_connection_settings.field.is_last.label');
		$is_last->explanation = t('cms.widget_connection_settings.field.is_last.explanation');
	}
}
