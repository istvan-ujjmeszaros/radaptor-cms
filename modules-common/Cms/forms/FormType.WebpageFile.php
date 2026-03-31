<?php

class FormTypeWebpageFile extends AbstractForm
{
	public const string ID = 'webpage_file';

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
			'path' => '/admin/resources/edit/',
			'resource_name' => 'file.html',
			'layout' => 'admin_default',
		];
	}

	public function hasRole(): bool
	{
		return Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN);
	}

	public function commit(): void
	{
		if ($this->getMode() == self::_MODE_UPDATE) {
			$this->savedata['node_id'] = $this->getItemId();

			$return_update = ResourceTreeHandler::updateResourceTreeEntry($this->savedata, $this->getItemId());

			if ($return_update) {
				SystemMessages::addSystemMessage(t('common.saved'));
			} else {
				SystemMessages::addSystemMessage(t('common.no_changes'));
			}
		}
	}

	public function setMetadata(): void
	{
		$this->_meta->title = t('cms.webpage_file.title_edit');
		$this->_meta->sub_title = ResourceTreeHandler::getResourceTreeEntryName($this->getItemId());
	}

	public function setInitValues(): void
	{
		$this->initvalues = ResourceTypeFile::getResourceData($this->getItemId());
	}

	public function makeInputs(): void
	{
		$resource_name = new FormInputText('resource_name', $this);
		$resource_name->label = t('cms.webpage_file.field.resource_name.label');
		$resource_name->explanation = t('cms.webpage_file.field.resource_name.explanation');
		$resource_name->addValidator(new FormValidatorNotEmpty(t('form.validation.required')));
		$v = new FormValidatorStringlength(t('form.validation.max_length', ['max' => 128]));
		$v->min = 0;
		$v->max = 128;
		$resource_name->addValidator($v);

		$title = new FormInputText('title', $this);
		$title->label = t('cms.webpage_file.field.title.label');
		$title->explanation = t('cms.webpage_file.field.title.explanation');
		$v = new FormValidatorStringlength(t('form.validation.max_length', ['max' => 255]));
		$v->min = 0;
		$v->max = 255;
		$title->addValidator($v);

		$text = new FormInputText('text', $this);
		$text->label = t('cms.webpage_file.field.text.label');
		$text->explanation = t('cms.webpage_file.field.text.explanation');
		$v = new FormValidatorStringlength(t('form.validation.max_length', ['max' => 65535]));
		$v->min = 0;
		$v->max = 65535;
		$text->addValidator($v);

		if (Roles::hasRole(RoleList::ROLE_ACL_VIEWER) || Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)) {
			$is_inheriting_acl = new FormInputCheckbox('is_inheriting_acl', $this);
			$is_inheriting_acl->label = t('cms.resource_acl.inherit_label');
			$is_inheriting_acl->explanation = t('cms.resource_acl.inherit_help');
			$is_inheriting_acl->initvalue = 1;
		}
	}
}
