<?php

class FormInputPassword extends FormInput
{
	public const string INPUTTYPE = 'password';

	public function getInputtype(): string
	{
		return self::INPUTTYPE;
	}
}
