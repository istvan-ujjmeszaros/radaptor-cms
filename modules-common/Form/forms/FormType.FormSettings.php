<?php

class FormTypeFormSettings extends AbstractForm
{
	public const string ID = 'form_settings';

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
			'resource_name' => 'form-settings.html',
			'layout' => 'admin_default',
		];
	}

	public static function getRequiredUrlParams(): array
	{
		return [
			'item_id' => t('cms.form.missing_widget_id'),
		];
	}

	public function hasRole(): bool
	{
		$connection_id = Request::_GET("item_id");

		if ($connection_id == '') {
			return false;
		}

		$resource_id = ResourceTreeHandler::getResourceIdFromConnectionId($connection_id);

		return ResourceAcl::canAccessResource($resource_id, ResourceAcl::_ACL_EDIT) && Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN);
	}

	public function commit(): void
	{
		if (is_null($this->getItemId())) {
			SystemMessages::_error(t('cms.form.no_id'));
		} elseif (AttributeHandler::addAttribute(new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, (string) $this->getItemId()), $this->savedata)) {
			SystemMessages::addSystemMessage(t('common.saved'));
		} else {
			SystemMessages::addSystemMessage(t('common.no_changes'));
		}
	}

	public function setMetadata(): void
	{
		$this->_meta->title = t('form.' . self::ID . '.title');
		$this->_meta->sub_title = t('cms.form.settings_subtitle', [
			'page' => WidgetConnection::getOwnerWebpageTitle($this->getItemId()),
		]);
	}

	public function setInitValues(): void
	{
		$this->initvalues = AttributeHandler::getAttributes(new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, (string) $this->getItemId()));
	}

	public function makeInputs(): void
	{
		$form_id = new FormInputSelect('form_id', $this);
		$form_id->label = t('cms.form.field.form_id.label');
		$form_id->values = Form::getVisibleFormTypes();
		$form_id->explanation = t('cms.form.field.form_id.explanation');
	}
}
