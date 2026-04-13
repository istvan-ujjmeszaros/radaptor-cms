<?php

class FormInputText extends FormInput
{
	public const string INPUTTYPE = 'text';

	public ?string $autocomplete_url = null;
	public ?string $connected_autocomplete_fieldname = null;

	public function getInputtype(): string
	{
		return self::INPUTTYPE;
	}
}
