<?php

class FormTypeWebpageDomain extends AbstractForm
{
	public const string ID = 'webpage_domain';

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
			'resource_name' => 'domain.html',
			'layout' => 'admin_default',
		];
	}

	public function hasRole(): bool
	{
		return Roles::hasRole(RoleList::ROLE_DOMAINS_ADMIN);
	}

	public function commit(): void
	{
		switch ($this->getMode()) {
			case self::_MODE_CREATE:
				$parent_id = 0;

				$this->savedata['node_type'] = 'root';

				$this->savedata['path'] = '/';

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
			$this->_meta->title = t('cms.webpage_domain.title_create');
		} else {
			$this->_meta->title = t('cms.webpage_domain.title_edit');
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
		$resource_name->label = t('cms.webpage_domain.field.resource_name.label');
		$resource_name->explanation = t('cms.webpage_domain.field.resource_name.explanation');
		$resource_name->addValidator(new FormValidatorNotEmpty(t('form.validation.required')));
		$v = new FormValidatorStringlength(t('form.validation.max_length', ['max' => 128]));
		$v->min = 0;
		$v->max = 128;
		$resource_name->addValidator($v);
	}
}
