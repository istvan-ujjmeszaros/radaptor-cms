<?php

class FormInputTextarea extends FormInput
{
	public const string INPUTTYPE = 'textarea';

	public const string EDITOR_AUTO = 'ckeditor';
	public const string EDITOR_TINYMCE = 'tinymce';
	public const string EDITOR_CODEMIRROR = 'codemirror';

	public const string TOOLBAR_FULL = 'Fulltext_Plugin';
	public const string TOOLBAR_TEXTONLY = 'Minimal';

	public string $editor = '';
	public string $toolbar = 'toolbar_Minimal';

	public function getInputtype(): string
	{
		return self::INPUTTYPE;
	}
}
