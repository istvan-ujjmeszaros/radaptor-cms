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
			'path' => '/',
			'resource_name' => 'login.html',
			'layout' => 'admin_login',
		];
	}

	public function hasRole(): bool
	{
		return true;
	}

	public function initializeValues(): void
	{
		// A logged-in user should not stay on the login page, unless they are
		// intentionally editing that page in the CMS.
		if (!$this->getTreeBuildContext()->isEditable() && !is_null(User::getCurrentUser())) {
			Url::redirect(Url::getCurrentHost());
		}
	}

	public function commit(): void
	{
		User::loginUser($this->savedata['username'], $this->savedata['password']);

		if (Request::_GET('loginreferer', false) !== false) {
			Url::redirect(Url::sanitizeRefererUrl((string) Request::_GET('loginreferer')));
		} elseif ($this->isCanonicalLoginResourceRequest()) {
			Url::redirect(Url::getCurrentHost());
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

	private function isCanonicalLoginResourceRequest(): bool
	{
		$page_id = ResourceTypeWebpage::getWebpageIdByFormType(FormList::USERLOGIN);

		if (!is_int($page_id) || $page_id <= 0) {
			return false;
		}

		$login_url = Url::getSeoUrl($page_id);

		if (!is_string($login_url) || trim($login_url) === '') {
			return false;
		}

		$current_path = parse_url(Url::getCurrentUrl(), PHP_URL_PATH);
		$login_path = parse_url($login_url, PHP_URL_PATH);

		if (!is_string($current_path) || !is_string($login_path) || $current_path === '' || $login_path === '') {
			return false;
		}

		return rtrim($current_path, '/') === rtrim($login_path, '/');
	}
}
