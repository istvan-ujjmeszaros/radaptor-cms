<?php

class FormTypeUsergroup extends AbstractForm
{
	public const string ID = 'usergroup';

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
			'path' => '/admin/usergroups/edit',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	public function hasRole(): bool
	{
		return Roles::hasRole(RoleList::ROLE_USERGROUPS_ADMIN);
	}

	public function commit(): void
	{
		switch ($this->getMode()) {
			case self::_MODE_CREATE:
				$parent_id = Request::_GET('ref_id');

				if (Usergroups::addUsergroup($this->savedata, $parent_id)) {
					SystemMessages::addSystemMessage(t('user.usergroup.saved'));
				} else {
					SystemMessages::addSystemMessage(t('user.usergroup.error_save'));
				}

				break;

			case self::_MODE_UPDATE:
				$this->savedata['node_id'] = $this->getItemId();

				$return_update = Usergroups::updateUsergroup($this->savedata, $this->getItemId());

				if ($return_update) {
					SystemMessages::addSystemMessage(t('user.usergroup.updated'));
				} else {
					SystemMessages::addSystemMessage(t('user.no_changes'));
				}

				break;
		}
	}

	public function setMetadata(): void
	{
		if ($this->_mode == self::_MODE_CREATE) {
			$this->_meta->title = t('user.usergroup.title_create');
		} else {
			$this->_meta->title = t('user.usergroup.title_edit');
			$data = Usergroups::getUsergroupValues($this->getItemId());
			$this->_meta->sub_title = $data['title'];
		}
	}

	public function setInitValues(): void
	{
		$this->initvalues = Usergroups::getUsergroupValues($this->getItemId());
	}

	public function makeInputs(): void
	{
		$title = new FormInputText('title', $this);
		$title->label = t('user.usergroup.field.title');
		$title->explanation = t('user.usergroup.field.title.explanation');
		$v = new FormValidatorStringlength(t('user.usergroup.validation.title_max_length'));
		$v->min = 0;
		$v->max = 255;
		$title->addValidator($v);
		$title->addValidator(new FormValidatorNotEmpty(t('form.validation.required')));

		$description = new FormInputTextarea('description', $this);
		$description->label = t('user.usergroup.field.description');
		$description->explanation = t('user.usergroup.field.description.explanation');

		$is_system_group = new FormInputCheckbox('is_system_group', $this);
		$is_system_group->label = t('user.usergroup.field.is_system_group');
		$is_system_group->explanation = t('user.usergroup.field.is_system_group.explanation');
	}
}
