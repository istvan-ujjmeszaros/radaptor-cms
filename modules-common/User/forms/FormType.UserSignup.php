<?php

class FormTypeUserSignup extends AbstractForm
{
	public const string ID = 'user_signup';

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
			'path' => '/',
			'resource_name' => 'signup.html',
			'layout' => 'admin_empty',
		];
	}

	public function hasRole(): bool
	{
		return true;
	}

	public function initializeValues(): void
	{
		// ha itt vagyunk (a login oldalon), amikor be van jelentkezve valaki,
		// akkor átirányítunk a kezdőoldalra (kivéve, ha szerkesztő módban
		// vagyunk), mert a bejelentkezésnek nincs értelme...
		if (!$this->getTreeBuildContext()->isEditable() && !is_null(User::getCurrentUser())) {
			Url::redirect(Url::getCurrentHost());
		}
	}

	public function commit(): void
	{
		$savedata = $this->savedata;

		unset($savedata['password2']);

		$savedata['is_active'] = 1;
		$savedata['locale'] = LocaleService::tryCanonicalize(Kernel::getLocale()) ?? LocaleService::getDefaultLocale();
		$savedata['password'] = User::encodePassword($savedata['password']);

		User::addUser($savedata);
		User::loginUser($savedata['username'], $this->savedata['password']);

		Url::redirect("/");
	}

	public function setMetadata(): void
	{
		$this->_meta->title = t('user.signup.title');
		$this->_meta->formButtonCancel = null;
		$this->_meta->formButtonSave = new FormButton(t('user.signup.button'), IconNames::LOGIN, FormButton::CLASS_STANDARD);
		$this->_meta->enableAutoReferer = false;
	}

	public function setInitValues(): void
	{
	}

	public function makeInputs(): void
	{
		$username = new FormInputText('username', $this);
		$username->label = t('user.field.username.label');
		//'explanation' => 'Írd be a felhasználónevet!',

		$password = new FormInputPassword('password', $this);
		$password->label = t('user.field.password.label');
		//'explanation' => 'Írd be a jelszót!',

		$password = new FormInputPassword('password2', $this);
		$password->label = t('user.field.password_confirm.label');
		//'explanation' => 'Írd be a jelszót!',
	}

	protected function _validateData(): void
	{
		parent::_validateData();

		$this->validateUsername();
		$this->validatePassword();
	}

	private function validateUsername(): void
	{
		if (mb_strlen((string) $this->getInput('username')) < 1) {
			$this->getInput('username')->addError(t('user.signup.error_username_required'));
		}

		$user = User::getUserByName($this->getInput('username'));

		if (!is_null($user)) {
			$this->getInput('username')->addError(t('user.signup.error_username_taken'));
		}
	}

	private function validatePassword(): void
	{
		if (mb_strlen((string) $this->getInput('password')) < 6) {
			$this->getInput('password')->addError(t('user.signup.error_password_min_length'));
		}

		if ($this->getInput('password')->getValue() !== $this->getInput('password2')->getValue()) {
			$this->getInput('password')->addError(t('user.validation.password_mismatch'));
			$this->getInput('password2')->addError(t('user.validation.password_mismatch'));
		}
	}
}
