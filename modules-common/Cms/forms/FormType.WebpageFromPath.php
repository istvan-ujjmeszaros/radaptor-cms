<?php

class FormTypeWebpageFromPath extends AbstractForm
{
	public const string ID = 'webpage_from_path';

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

	public function hasRole(): bool
	{
		return Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN);
	}

	public function commit(): void
	{
		$page_id = ResourceTreeHandler::createResourceTreeEntryFromPath(Request::_GET('folder'), Request::_GET('resource', 'index.html'), 'webpage', $this->savedata['layout']);

		if ($page_id) {
			SystemMessages::addSystemMessage(t('common.saved'));
		} else {
			SystemMessages::addSystemMessage(t('common.error_save'));
		}
	}

	public function setMetadata(): void
	{
		if ($this->_mode != self::_MODE_CREATE) {
			return;
		}

		$this->_meta->title = '<span style="color:red">' . t('cms.webpage_from_path.title') . '</span>';
		$this->_meta->sub_title = t('cms.webpage_from_path.subtitle');
		$this->_meta->formButtonCancel = null;
	}

	public function setInitValues(): void
	{
		$this->initvalues = ResourceTreeHandler::getResourceTreeEntryDataById($this->getItemId());
	}

	public function makeInputs(): void
	{
		$layout = new FormInputSelect('layout', $this);
		$layout->label = t('cms.webpage.field.layout.label');
		$layout->values = Layout::getLayoutListForSelect();
		$layout->explanation = t('cms.webpage.field.layout.explanation');
		$layout->addValidator(new FormValidatorSelected(t('form.validation.required')));
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/webpage-from-path/',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}
}
