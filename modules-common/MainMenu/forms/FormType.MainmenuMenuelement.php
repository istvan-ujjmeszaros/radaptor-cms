<?php

class FormTypeMainmenuMenuelement extends AbstractForm
{
	public const string ID = 'main_menu_item';

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
			'path' => '/admin/components/mainmenu/edit/',
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
		$type = $this->savedata['type'];
		unset($this->savedata['type']);

		if ($type == 'belso') {
			$this->savedata['url'] = null;
		} else {
			$this->savedata['page_id'] = null;
		}

		if ($this->savedata['page_id'] == '') {
			$this->savedata['page_id'] = null;
		}

		switch ($this->getMode()) {
			case self::_MODE_CREATE:
				$parent_id = Request::_GET('ref_id', 0);

				$new_id = MainMenu::addMenu($this->savedata, $parent_id);

				if ($new_id) {
					SystemMessages::addSystemMessage(t('cms.menu.saved'));
				} else {
					SystemMessages::addSystemMessage(t('common.error_save'));
				}

				break;

			case self::_MODE_UPDATE:

				$changed = MainMenu::updateMenu($this->savedata, $this->getItemId());

				if ($changed) {
					SystemMessages::addSystemMessage(t('cms.menu.updated'));
				} else {
					SystemMessages::addSystemMessage(t('common.no_changes'));
				}

				break;
		}
	}

	public function setMetadata(): void
	{
		if ($this->_mode == self::_MODE_CREATE) {
			$this->_meta->title = t('cms.menu.form.title_create');
		} else {
			$this->_meta->title = t('cms.menu.form.title_edit');
			$this->_meta->sub_title = MainMenu::getMenuName($this->getItemId());
		}
	}

	public function setInitValues(): void
	{
		$this->initvalues = MainMenu::getMenuValues($this->getItemId());

		if ($this->initvalues['url'] == '') {
			$this->initvalues['type'] = 'belso';
		} else {
			$this->initvalues['type'] = 'kulso';
		}

		if ($this->initvalues['url'] == '') {
			$this->initvalues['url'] = 'https://';
		}
	}

	public function makeInputs(): void
	{
		$node_name = new FormInputText('node_name', $this);
		$node_name->label = t('cms.menu.field.node_name.label');

		$node_name->addValidator(new FormValidatorNotEmpty(t('form.validation.required')));
		$v = new FormValidatorStringlength(t('cms.menu.validation.node_name_max_length'));
		$v->min = 0;
		$v->max = 255;
		$node_name->addValidator($v);

		$type = new FormInputRadiogroup('type', $this);
		$type->label = t('cms.menu.field.type.label');
		$type->values = [
			t('cms.menu.field.type.internal') => 'belso',
			t('cms.menu.field.type.external') => 'kulso',
		];
		$type->addValidator(new FormValidatorSelected(t('cms.menu.validation.type_required')));

		$url = new FormInputText('url', $this);
		$url->label = t('cms.menu.field.url.label');

		if ($url->getValue() == '') {
			$url->setValue('https://');
		}
		$url->explanation = t('cms.menu.field.url.explanation');
		$url->addValidator(new FormValidatorNotEmpty(t('cms.menu.validation.url_required')));
		$v = new FormValidatorStringlength(t('cms.menu.validation.url_max_length'));
		$v->min = 0;
		$v->max = 255;
		$url->addValidator($v);

		$page_id = new FormInputSelect('page_id', $this);
		$page_id->label = t('cms.menu.field.page.label');
		$page_id->required = false;
		$page_id->values = ResourceTreeHandler::getResourceListForSelect('webpage');
		$page_id->explanation = t('cms.menu.field.page.explanation');
	}
}
