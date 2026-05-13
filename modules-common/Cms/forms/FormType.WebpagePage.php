<?php

class FormTypeWebpagePage extends AbstractForm
{
	public const string ID = 'webpage_page';

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
			'resource_name' => 'webpage.html',
			'layout' => 'admin_default',
		];
	}

	public function hasRole(): bool
	{
		return Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN);
	}

	public function commit(): void
	{
		if (isset($this->savedata['resource_name'])) {
			$pathinfo = pathinfo((string) $this->savedata['resource_name']);

			if (!isset($pathinfo['extension']) || $pathinfo['extension'] != 'html') {
				$this->savedata['resource_name'] .= '.html';
			}
		}

		switch ($this->getMode()) {
			case self::_MODE_CREATE:

				$parent_id = Request::_GET('ref_id');

				$this->savedata['node_type'] = 'webpage';
				$this->savedata['locale'] = ResourceLocaleFormHelper::resolveSubmittedLocale($this, (int) $parent_id);

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
				$this->savedata['locale'] = ResourceLocaleFormHelper::resolveSubmittedLocale($this, (int) $this->getItemId(), true);

				if (ResourceTreeHandler::updateResourceTreeEntry($this->savedata, $this->getItemId())) {
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
			$this->_meta->title = t('cms.webpage_page.title_create');
		} else {
			$this->_meta->title = t('cms.webpage_page.title_edit');
			$this->_meta->sub_title = ResourceTreeHandler::getResourceTreeEntryName($this->getItemId());
		}
	}

	public function setInitValues(): void
	{
		$this->initvalues = ResourceTypeWebpage::getResourceData($this->getItemId(), Themes::extraAttributes($this));
	}

	public function makeInputs(): void
	{
		$title = new FormInputText('title', $this);
		$title->label = t('cms.webpage_page.field.title.label');
		$title->explanation = t('cms.webpage_page.field.title.explanation');
		$v = new FormValidatorStringlength(t('form.validation.max_length', ['max' => 255]));
		$v->min = 0;
		$v->max = 255;
		$title->addValidator($v);

		$resource_name = new FormInputText('resource_name', $this);
		$resource_name->label = t('cms.webpage_page.field.resource_name.label');
		$resource_name->explanation = t('cms.webpage_page.field.resource_name.explanation');
		$resource_name->addValidator(new FormValidatorNotEmpty(t('form.validation.required')));
		$v = new FormValidatorStringlength(t('form.validation.max_length', ['max' => 128]));
		$v->min = 0;
		$v->max = 128;
		$resource_name->addValidator($v);

		$layout = new FormInputSelect('layout', $this);
		$layout->label = t('cms.webpage.field.layout.label');
		$layout->values = Layout::getLayoutListForSelect();
		$layout->explanation = t('cms.webpage.field.layout.explanation');
		$layout->addValidator(new FormValidatorSelected(t('form.validation.required')));

		ResourceLocaleFormHelper::addLocaleInput($this, (int) ($this->getMode() === self::_MODE_CREATE ? Request::_GET('ref_id', 0) : $this->getItemId()));

		$keywords = new FormInputText('keywords', $this);
		$keywords->label = t('cms.webpage_page.field.keywords.label');
		$keywords->explanation = t('cms.webpage_page.field.keywords.explanation');
		$v = new FormValidatorStringlength(t('form.validation.max_length', ['max' => 255]));
		$v->min = 0;
		$v->max = 255;
		$keywords->addValidator($v);

		$description = new FormInputTextarea('description', $this);
		$description->label = t('cms.webpage_page.field.description.label');
		$description->explanation = t('cms.webpage_page.field.description.explanation');

		/*
		  $robots_index = new FormInputRadiogroup('robots_index', $this);
		  $robots_index->label = 'INDEX';
		  $robots_index->values = array(
		  'Az oldal szerepeljen a keresőkben' => '1',
		  'Keresők nem indexelhetik az oldalt' => '0',
		  );
		  $robots_index->initvalue = '1';
		  $robots_index->addValidator(new FormValidatorSelected('Jelöld be, hogy engedélyezett legyen-e az oldal indexelése a keresők számára'));

		  $robots_follow = new FormInputRadiogroup('robots_follow', $this);
		  $robots_follow->label = 'FOLLOW';
		  $robots_follow->values = array(
		  'A keresők követhetik a linkeket' => '1',
		  'A linkek követése tiltva' => '0',
		  );
		  $robots_follow->initvalue = '1';
		  $robots_follow->addValidator(new FormValidatorSelected('Jelöld be, hogy engedélyezett legyen-e a linkek követése a keresők számára'));
		 */

		if (Roles::hasRole(RoleList::ROLE_ACL_VIEWER) || Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)) {
			$is_inheriting_acl = new FormInputCheckbox('is_inheriting_acl', $this);
			$is_inheriting_acl->label = t('cms.resource_acl.inherit_label');
			$is_inheriting_acl->explanation = t('cms.resource_acl.inherit_help');
			$is_inheriting_acl->initvalue = 1;
		}

		Themes::initExtraWebpageFormInputs($this);
	}
}
