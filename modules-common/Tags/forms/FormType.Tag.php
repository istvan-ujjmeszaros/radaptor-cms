<?php

class FormTypeTag extends FormCustomValidatorTag
{
	public const string ID = 'tag';

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
			'path' => '/tags/edit/',
			'resource_name' => 'index.html',
			'layout' => 'public_default',
		];
	}

	public function hasRole(): bool
	{
		return Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER);
	}

	public function commit(): void
	{
		switch ($this->getMode()) {
			case self::_MODE_CREATE:

				if (EntityTag::addTag($this->savedata)) {
					SystemMessages::addSystemMessage(t('tags.saved'));
				} else {
					SystemMessages::addSystemMessage(t('common.error_save'));
				}

				break;

			case self::_MODE_UPDATE:

				if (EntityTag::updateTag($this->savedata, $this->getItemId())) {
					SystemMessages::addSystemMessage(t('tags.updated'));
				} else {
					SystemMessages::addSystemMessage(t('common.no_changes'));
				}

				break;
		}
	}

	public function setMetadata(): void
	{
		if ($this->_mode == self::_MODE_CREATE) {
			$this->_meta->title = t('tags.form.title_create');
		} else {
			$this->_meta->title = t('tags.form.title_edit');
			$this->_meta->sub_title = EntityTag::getTagDisplayName($this->getItemId());
		}
	}

	public function setInitValues(): void
	{
		$this->initvalues = EntityTag::getTagValues($this->getItemId());
	}

	public function makeInputs(): void
	{
		$name = new FormInputText('name', $this);
		$name->label = t('tags.field.name.label');
		$name->explanation = t('tags.field.name.explanation');
		$name->addValidator(new FormValidatorNotEmpty(t('form.validation.required')));
		$v = new FormValidatorStringlength(t('tags.validation.name_max_length'));
		$v->min = 0;
		$v->max = 255;
		$name->addValidator($v);

		if ($this->getMode() == self::_MODE_CREATE) {
			$context = new FormInputSelect('context', $this);
			$context->label = t('tags.field.type.label');
			$context->explanation = t('tags.field.type.explanation');

			$contextOptions = [];

			foreach (PackageTagContextRegistry::getAll() as $contextKey => $contextData) {
				$contextOptions[] = [
					'inputtype' => 'option',
					'value' => $contextKey,
					'label' => $contextData['label'] ?? $contextKey,
				];
			}

			$context->values = $contextOptions;
			$context->addValidator(new FormValidatorSelected(t('form.validation.required')));
		}

		$__description = new FormInputTextarea('__description', $this);
		$__description->label = t('tags.field.description.label');
		$__description->editor = FormInputTextarea::EDITOR_AUTO;
	}
}
