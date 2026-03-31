<?php

class FormTypeRichTextContentSelect extends AbstractForm
{
	public const string ID = 'rich_text_select';

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
			'path' => '/admin/components/richtext/selector/',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	public function hasRole(): bool
	{
		return Roles::hasRole(RoleList::ROLE_RICHTEXT_ADMINISTRATOR);
	}

	public function commit(): void
	{
		switch ($this->getMode()) {
			case self::_MODE_UPDATE:
			case self::_MODE_CREATE:

				$connection_id = Request::_GET('connection_id', Request::DEFAULT_ERROR);

				if (AttributeHandler::addAttribute(new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, $connection_id), ['content_id' => $this->savedata['content_id']])) {
					SystemMessages::addSystemMessage(t('cms.richtext.assigned'));
				} else {
					SystemMessages::addSystemMessage(t('common.error_save'));
				}

				break;
		}
	}

	public function setMetadata(): void
	{
		if ($this->_mode == self::_MODE_CREATE) {
			$this->_meta->title = t('cms.richtext.select.form.title');
		} else {
			$this->_meta->title = t('cms.richtext.select.form.title');
			$this->_meta->sub_title = EntityRichtext::getContentTitle($this->getItemId());
		}
	}

	public function setInitValues(): void
	{
		$data = AttributeHandler::getAttributes(new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, Request::_GET('connection_id')));

		$this->initvalues = $data;
	}

	public function makeInputs(): void
	{
		$content_id = new FormInputSelect('content_id', $this);
		$content_id->label = t('cms.richtext.field.content.label');
		$content_id->values = EntityRichtext::getListForSelect();
	}
}
