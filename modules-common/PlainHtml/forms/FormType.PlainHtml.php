<?php

class FormTypePlainHtml extends AbstractForm
{
	public const string ID = 'plain_html';

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
			'path' => '/admin/components/plain-html/edit/',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	public function hasRole(): bool
	{
		return Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN);
	}

	public function commit(): void
	{
		$connection_id = $this->getItemId();

		if (!is_null($connection_id) && PlainHtml::saveSettings($this->savedata, $connection_id)) {
			SystemMessages::addSystemMessage(t('common.saved'));
		} else {
			SystemMessages::addSystemMessage(t('common.no_changes'));
		}
	}

	public function setMetadata(): void
	{
		$this->_meta->title = t('cms.plainhtml.form.title');

		if ($this->getItemId()) {
			$this->_meta->sub_title = ResourceTreeHandler::getResourceTreeEntryName($this->getItemId());
		}
	}

	public function setInitValues(): void
	{
		$this->initvalues = PlainHtml::getSettings($this->getItemId());
	}

	public function makeInputs(): void
	{
		$content = new FormInputTextarea('content', $this);
		$content->label = t('cms.plainhtml.field.content.label');
		$content->explanation = t('cms.plainhtml.field.content.explanation');
		$content->editor = FormInputTextarea::EDITOR_CODEMIRROR;
	}
}
