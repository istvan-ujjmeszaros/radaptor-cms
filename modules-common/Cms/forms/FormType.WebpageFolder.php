<?php

class FormTypeWebpageFolder extends AbstractForm
{
	public const string ID = 'webpage_folder';

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
			'resource_name' => 'folder.html',
			'layout' => 'admin_default',
		];
	}

	public function hasRole(): bool
	{
		return Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN);
	}

	public function commit(): void
	{
		switch ($this->getMode()) {
			case self::_MODE_CREATE:
				$parent_id = Request::_GET('ref_id');

				$this->savedata['node_type'] = 'folder';

				if ($parent_id == 0) {
					$this->savedata['path'] = '/';
				} else {
					$parent_info = ResourceTreeHandler::getResourceTreeEntryDataById($parent_id);
					$this->savedata['path'] = $parent_info['path'] . $parent_info['resource_name'] . '/';
				}

				if (ResourceTreeHandler::addResourceEntry($this->savedata, $parent_id)) {
					SystemMessages::addSystemMessage(t('common.saved'));
				} else {
					SystemMessages::addSystemMessage(t('common.error_save'));
				}

				break;

			case self::_MODE_UPDATE:
				$this->savedata['node_id'] = $this->getItemId();

				$return_update = ResourceTreeHandler::updateResourceTreeEntry($this->savedata, $this->getItemId());

				if ($return_update) {
					ResourceTreeHandler::rebuildPath($this->getItemId());
					SystemMessages::addSystemMessage(t('common.saved'));
				} else {
					SystemMessages::addSystemMessage(t('common.no_changes'));
				}

				break;
		}
	}

	public function setMetadata(): void
	{
		if ($this->_mode == self::_MODE_CREATE) {
			$this->_meta->title = t('cms.webpage_folder.title_create');
		} else {
			$this->_meta->title = t('cms.webpage_folder.title_edit');
			$this->_meta->sub_title = ResourceTreeHandler::getResourceTreeEntryName($this->getItemId());
		}
	}

	public function setInitValues(): void
	{
		$this->initvalues = ResourceTypeWebpage::getResourceData($this->getItemId());
	}

	public function makeInputs(): void
	{
		$resource_name = new FormInputText('resource_name', $this);
		$resource_name->label = t('cms.webpage_folder.field.resource_name.label');
		$resource_name->explanation = t('cms.webpage_folder.field.resource_name.explanation');
		$resource_name->addValidator(new FormValidatorNotEmpty(t('form.validation.required')));
		$v = new FormValidatorStringlength(t('form.validation.max_length', ['max' => 128]));
		$v->min = 0;
		$v->max = 128;
		$resource_name->addValidator($v);

		if (Roles::hasRole(RoleList::ROLE_ACL_VIEWER) || Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)) {
			$is_inheriting_acl = new FormInputCheckbox('is_inheriting_acl', $this);
			$is_inheriting_acl->label = t('cms.resource_acl.inherit_label');
			$is_inheriting_acl->explanation = t('cms.resource_acl.inherit_help');
			$is_inheriting_acl->initvalue = 1;
		}
	}
}
