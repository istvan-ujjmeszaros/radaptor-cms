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

				if (RichTextLocaleService::hasRichTextLocaleColumn()) {
					$this->savedata['locale'] = $this->resolveLocale();
				}

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

				if (RichTextLocaleService::hasRichTextLocaleColumn()) {
					$this->savedata['locale'] = $this->resolveLocale();
				}

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

		$this->addLocaleInput();

		$content = new FormInputTextarea('__content', $this);
		$content->editor = FormInputTextarea::EDITOR_AUTO;
		$content->toolbar = FormInputTextarea::TOOLBAR_FULL;
		$content->label = t('cms.richtext.field.content.label');
		$content->explanation = t('cms.richtext.field.content.explanation');
	}

	private function addLocaleInput(): void
	{
		if (!RichTextLocaleService::hasRichTextLocaleColumn()) {
			return;
		}

		if (Request::_GET('connection_id', null) !== null) {
			return;
		}

		$enabled_locales = LocaleService::enabledForNewContent();
		$current_locale = LocaleService::tryCanonicalize((string) ($this->initvalues['locale'] ?? ''));

		if (count($enabled_locales) <= 1 && ($current_locale === null || $current_locale === ($enabled_locales[0] ?? null))) {
			return;
		}

		$locale = new FormInputSelect('locale', $this);
		$locale->label = t('cms.richtext.field.locale.label');
		$locale->explanation = t('cms.richtext.field.locale.explanation');
		$locale->initvalue = $current_locale ?? RichTextLocaleService::getLocaleForCurrentRequest();
		$locale->values = LocaleRegistry::buildSelectOptions(LocaleService::allForExistingContentEditing($current_locale));
	}

	private function resolveLocale(): string
	{
		$submitted = $this->savedata['locale'] ?? null;

		if (is_string($submitted) && trim($submitted) !== '') {
			return LocaleService::canonicalize($submitted);
		}

		$current_locale = LocaleService::tryCanonicalize((string) ($this->initvalues['locale'] ?? ''));

		if ($current_locale !== null) {
			return $current_locale;
		}

		return RichTextLocaleService::getLocaleForCurrentRequest();
	}
}
