<?php

class FormTypeUser extends AbstractForm
{
	public const string ID = 'user';

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
			'path' => '/admin/users/edit',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	public function hasRole(): bool
	{
		return Roles::hasRole(RoleList::ROLE_USERS_ADMIN);
	}

	public function commit(): void
	{
		switch ($this->getMode()) {
			case self::_MODE_CREATE:

				if (User::addUser($this->savedata)) {
					SystemMessages::addSystemMessage(t('user.saved'));
				} else {
					SystemMessages::addSystemMessage(t('user.error_save'));
				}

				break;

			case self::_MODE_UPDATE:

				if (User::updateUser($this->savedata, $this->getItemId())) {
					SystemMessages::addSystemMessage(t('user.updated'));

					// ha a saját adatainkat módosítjuk, akkor frissíteni kell
					// a _SESSION-t, hogy ne dobjon ki a rendszer
					if ($this->getItemId() == User::getCurrentUserId()) {
						User::refreshUserSession();
					}
				} else {
					SystemMessages::addSystemMessage(t('user.no_changes'));
				}

				break;
		}
	}

	public function setMetadata(): void
	{
		if ($this->_mode == self::_MODE_CREATE) {
			$this->_meta->title = t('user.form.title_create');
		} else {
			$this->_meta->title = t('user.form.title_edit');
			$this->_meta->sub_title = User::getNameById($this->getItemId());
		}

		$this->_meta->autocomplete = false;
	}

	public function setInitValues(): void
	{
		$this->initvalues = User::getUserFromId($this->getItemId());
	}

	public function makeInputs(): void
	{
		$username = new FormInputText('username', $this);
		$username->label = t('user.field.username.label');
		$username->explanation = t('user.field.username.explanation');
		$username->addValidator(new FormValidatorNotEmpty(t('form.validation.required')));
		$v = new FormValidatorStringlength(t('user.validation.username_max_length'));
		$v->min = 0;
		$v->max = 32;
		$username->addValidator($v);

		$passwd1 = new FormInputPassword('passwd1', $this);
		$passwd1->save = false;
		$passwd1->label = t('user.field.password.label');
		$passwd1->explanation = t('user.field.password.explanation');
		$v = new FormValidatorStringlength(t('user.validation.password_max_length'));
		$v->min = 0;
		$v->max = 255;
		$passwd1->addValidator($v);

		$passwd2 = new FormInputPassword('passwd2', $this);
		$passwd2->save = false;
		$passwd2->label = t('user.field.password_confirm.label');
		$passwd2->explanation = t('user.field.password_confirm.explanation');
		$v = new FormValidatorStringlength(t('user.validation.password_max_length'));
		$v->min = 0;
		$v->max = 255;
		$passwd2->addValidator($v);

		$timezone = new FormInputText('timezone', $this);
		$timezone->label = t('user.field.timezone.label');
		$timezone->explanation = t('user.field.timezone.explanation');
		$v = new FormValidatorStringlength(t('user.validation.timezone_invalid'));
		$v->min = 0;
		$v->max = 64;
		$timezone->addValidator($v);

		$locale = new FormInputSelect('locale', $this);
		$locale->label = t('user.field.locale.label');
		$locale->explanation = t('user.field.locale.explanation');
		$current_locale = LocaleService::tryCanonicalize((string) ($this->initvalues['locale'] ?? ''));
		$locale->initvalue = $current_locale ?? LocaleService::getDefaultLocale();
		$locale->values = $this->buildLocaleOptions($current_locale);
	}

	/**
	 * @return list<array{value: string, label: string}>
	 */
	private function buildLocaleOptions(?string $current_locale): array
	{
		$enabled_locales = LocaleService::enabledForUserChoice();
		$enabled = array_fill_keys($enabled_locales, true);
		$locales = $enabled_locales;

		if ($current_locale !== null && !in_array($current_locale, $locales, true)) {
			$locales[] = $current_locale;
		}

		$options = [];

		foreach ($locales as $locale) {
			$label = LocaleRegistry::getDisplayLabel($locale);

			if (!isset($enabled[$locale])) {
				$label .= ' (' . t('locale_admin.status.disabled') . ', ' . t('user.locale.current_label') . ')';
			}

			$options[] = [
				'value' => $locale,
				'label' => $label,
			];
		}

		return $options;
	}

	protected function _validateData(): void
	{
		parent::_validateData();

		$this->validateUsername();
		$this->validatePassword();
		$this->validateTimezone();
	}

	private function validateUsername(): void
	{
		$user_id = User::autoDetectUserId($this->getInput('username')->getValue());

		switch ($user_id) {
			case UserErrorCode::ERROR_USER_NOT_FOUND->value:
			case UserErrorCode::ERROR_USER_ID_EMPTY->value:
				// These are valid cases
				break;

			case UserErrorCode::ERROR_MULTIPLE_USERS->value:
				$this->getInput('username')->addError(t('user.validation.username_taken'));

				break;
		}

		if ($user_id > 0 && ($this->getMode() == self::_MODE_CREATE || $user_id !== $this->getItemId())) {
			$this->getInput('username')->addError(t('user.validation.username_taken'));
		}
	}

	private function validatePassword(): void
	{
		if ($this->getInput('passwd1')->getValue() != $this->getInput('passwd2')->getValue()) {
			$this->getInput('passwd2')->addError(t('user.validation.password_mismatch'));
		}

		if ($this->getMode() == self::_MODE_CREATE && $this->getInput('passwd1')->getValue() == '') {
			$this->getInput('passwd1')->addError(t('user.validation.password_required'));
		}

		if ($this->getInput('passwd1')->getValue()) {
			$this->savedata['password'] = User::encodePassword($this->getInput('passwd1')->getValue());
		} else {
			unset($this->savedata['password']);
		}
	}

	private function validateTimezone(): void
	{
		$timezone = trim((string) $this->getInput('timezone')->getValue());

		if ($timezone === '') {
			// Explicitly clear stored timezone; empty means fallback to UTC/default.
			$this->savedata['timezone'] = null;

			return;
		}

		try {
			new DateTimeZone($timezone);
		} catch (Exception) {
			$this->getInput('timezone')->addError(t('user.validation.timezone_invalid'));
		}
	}
}
