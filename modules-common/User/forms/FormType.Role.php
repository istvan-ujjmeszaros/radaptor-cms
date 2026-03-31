<?php

class FormTypeRole extends AbstractForm
{
	public const string ID = 'role';

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
			'path' => '/admin/roles/edit/',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	public function hasRole(): bool
	{
		return Roles::hasRole(RoleList::ROLE_ROLES_ADMIN);
	}

	public function commit(): void
	{
		switch ($this->getMode()) {
			case self::_MODE_CREATE:
				$parent_id = Request::_GET('ref_id');

				if (Roles::addRole($this->savedata, $parent_id)) {
					SystemMessages::addSystemMessage(t('user.role.saved'));
				} else {
					SystemMessages::addSystemMessage(t('user.role.error_save'));
				}

				break;

			case self::_MODE_UPDATE:
				$this->savedata['node_id'] = $this->getItemId();

				$return_update = Roles::updateRole($this->savedata, $this->getItemId());

				if ($return_update) {
					SystemMessages::addSystemMessage(t('user.role.updated'));
				} else {
					SystemMessages::addSystemMessage(t('user.no_changes'));
				}

				break;
		}
	}

	public function setMetadata(): void
	{
		if ($this->_mode == self::_MODE_CREATE) {
			$this->_meta->title = t('user.role.title_create');
		} else {
			$this->_meta->title = t('user.role.title_edit');
			$data = Roles::getRoleValues($this->getItemId());
			$this->_meta->sub_title = $data['title'];
		}
	}

	public function setInitValues(): void
	{
		$this->initvalues = Roles::getRoleValues($this->getItemId());
	}

	public function makeInputs(): void
	{
		$role = new FormInputText('role', $this);
		$role->label = t('user.role.field.role');
		$role->explanation = t('user.role.field.role.explanation');
		$v = new FormValidatorStringlength(t('user.role.validation.role_max_length'));
		$v->min = 0;
		$v->max = 32;
		$role->addValidator($v);
		$role->addValidator(new FormValidatorNotEmpty(t('form.validation.required')));
		$role->addValidator(new FormValidatorUnderscoreableLetters(t('user.role.validation.role_invalid_chars')));

		$title = new FormInputText('title', $this);
		$title->label = t('user.role.field.title');
		$title->explanation = t('user.role.field.title.explanation');
		$v = new FormValidatorStringlength(t('user.role.validation.title_max_length'));
		$v->min = 0;
		$v->max = 255;
		$title->addValidator($v);
		$title->addValidator(new FormValidatorNotEmpty(t('form.validation.required')));

		$description = new FormInputTextarea('description', $this);
		$description->label = t('user.role.field.description');
		$description->explanation = t('user.role.field.description.explanation');
	}
}
