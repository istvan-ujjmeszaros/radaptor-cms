<?php

class FormMetadata
{
	public string $title = '';
	public string $sub_title = '';
	public string $template = '';

	public ?FormButton $formButtonCancel;

	public ?FormButton $formButtonSave;
	public string $labelWidth = '';
	public string $postJavascriptFile = '';
	public bool $focusable = true;
	public bool $autocomplete = true;
	public string $errorHeader;
	public bool $useErrorWindow = true;
	public bool $enableAutoReferer = true;

	public function __construct()
	{
		// Setting up the default 'cancel' and 'submit' buttons
		$this->formButtonCancel = new FormButton(t('common.cancel'), IconNames::FORM_CANCEL, FormButton::CLASS_NEGATIVE);
		$this->formButtonSave = new FormButton(t('common.save'), IconNames::FORM_SAVE, FormButton::CLASS_POSITIVE);
		$this->errorHeader = t('common.error');
		$this->template = Config::APP_FORM_DEFAULT_TEMPLATE->value();
	}

	public function __set(string $name, mixed $value): never
	{
		Kernel::abort('Set unknown FormMetadata property is prohibited: ' . $name);
	}

	public function __get(string $name): never
	{
		Kernel::abort('Get unknown FormMetadata property is prohibited: ' . $name);
	}
}
