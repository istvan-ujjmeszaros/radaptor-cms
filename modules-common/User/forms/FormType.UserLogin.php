<?php

class FormTypeUserLogin extends AbstractForm
{
	public const string ID = 'user_login';

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
			'path' => '/admin/',
			'resource_name' => 'login.html',
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
		User::loginUser($this->savedata['username'], $this->savedata['password']);

		if (Request::_GET('loginreferer', false) !== false) {
			Url::redirect(Url::sanitizeRefererUrl((string) Request::_GET('loginreferer')));
		} else {
			Url::redirect(Url::getCurrentUrl());
		}
	}

	public function setMetadata(): void
	{
		$this->_meta->title = t('user.login.title');
		$this->_meta->formButtonCancel = null;
		$this->_meta->formButtonSave = new FormButton(t('user.login.button'), IconNames::LOGIN, FormButton::CLASS_STANDARD);
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
	}

	protected function _validateData(): void
	{
		parent::_validateData();

		$this->validateLogin();
	}

	private function validateLogin(): void
	{
		if (is_null(User::getUserByUsernameAndPassword($this->getInput('username'), $this->getInput('password')))) {
			$this->getInput('username')->addError(t('user.login.error_invalid_credentials'));
		}
	}
}
