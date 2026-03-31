<?php

class FormTypeRichText extends FormCustomValidatorRichText
{
	public const string ID = 'rich_text';

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
			'path' => '/admin/components/richtext/edit/',
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
			case self::_MODE_CREATE:

				$connection_id = Request::_GET('connection_id', Request::DEFAULT_ERROR);

				$content_id = EntityRichtext::createFromArray($this->savedata)->pkey();

				if ($content_id) {
					SystemMessages::addSystemMessage(t('cms.richtext.saved'));

					if (AttributeHandler::addAttribute(new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, $connection_id), ['content_id' => $content_id])) {
						SystemMessages::addSystemMessage(t('cms.richtext.assigned'));
					}
				} else {
					SystemMessages::addSystemMessage(t('common.error_save'));
				}

				break;

			case self::_MODE_UPDATE:

				EntityRichtext::updateById($this->getItemId(), $this->savedata);
				SystemMessages::addSystemMessage(t('cms.richtext.updated'));

				break;
		}
	}

	public function setMetadata(): void
	{
		if ($this->_mode == self::_MODE_CREATE) {
			$this->_meta->title = t('cms.richtext.form.title_create');
		} else {
			$this->_meta->title = t('cms.richtext.form.title_edit');
			$this->_meta->sub_title = EntityRichtext::getContentTitle($this->getItemId());
		}
	}

	public function setInitValues(): void
	{
		$this->initvalues = EntityRichtext::findById($this->getItemId())?->dto();
	}

	public function makeInputs(): void
	{
		$name = new FormInputText('name', $this);
		$name->label = t('cms.richtext.field.name.label');
		$name->explanation = t('cms.richtext.field.name.explanation');
		$name->addValidator(new FormValidatorNotEmpty(t('form.validation.required')));

		$title = new FormInputText('title', $this);
		$title->label = t('cms.richtext.field.title.label');
		$title->explanation = t('cms.richtext.field.title.explanation');

		$content = new FormInputTextarea('__content', $this);
		$content->editor = FormInputTextarea::EDITOR_AUTO;
		$content->toolbar = FormInputTextarea::TOOLBAR_FULL;
		$content->label = t('cms.richtext.field.content.label');
		$content->explanation = t('cms.richtext.field.content.explanation');
	}
}
