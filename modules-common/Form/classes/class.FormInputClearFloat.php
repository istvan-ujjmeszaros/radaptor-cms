<?php

class FormInputClearFloat extends FormInput
{
	public const string INPUTTYPE = 'clearfloat';

	public bool $save = false;

	public function getInputtype(): string
	{
		return self::INPUTTYPE;
	}
}
