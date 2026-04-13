<?php

class FormTypeJsTreeSettings extends AbstractForm
{
	public const string ID = 'js_tree_settings';

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
			'path' => '/admin/',
			'resource_name' => 'jstree-settings.html',
			'layout' => 'admin_default',
		];
	}

	public function hasRole(): bool
	{
		return Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER);
	}

	public function commit(): void
	{
		if (is_null($this->getItemId())) {
			SystemMessages::_error(t('cms.tree.no_connection_id'));
		} elseif (AttributeHandler::addAttribute(new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, (string) $this->getItemId()), $this->savedata)) {
			SystemMessages::addSystemMessage(t('common.saved'));
		} else {
			SystemMessages::addSystemMessage(t('common.no_changes'));
		}
	}

	public function setMetadata(): void
	{
		$this->_meta->title = t('form.' . self::ID . '.title');
		$this->_meta->sub_title = t('cms.form.settings_subtitle', ['page' => WidgetConnection::getOwnerWebpageTitle($this->getItemId())]);
	}

	public function setInitValues(): void
	{
		$this->initvalues = AttributeHandler::getAttributes(new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, (string) $this->getItemId()));
	}

	public function makeInputs(): void
	{
		$jstree_id = new FormInputSelect('jstree_type', $this);
		$jstree_id->label = t('cms.tree.field.type.label');
		$jstree_id->required = false;
		/* 		'no_required'=>1, */
		$jstree_id->values = [
			[
				'inputtype' => 'option',
				'value' => 'webpages',
				'label' => t('admin.menu.resource_tree'),
			],
			[
				'inputtype' => 'option',
				'value' => 'mainMenu',
				'label' => t('cms.menu.root'),
			],
			[
				'inputtype' => 'option',
				'value' => 'adminMenu',
				'label' => t('admin.menu.admin_menu'),
			],
		];

		$jstree_id->explanation = t('cms.tree.field.type.explanation');
	}
}
