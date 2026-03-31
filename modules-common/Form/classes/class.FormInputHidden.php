<?php

class FormInputHidden extends FormInput
{
	public const string INPUTTYPE = 'hidden';

	public function getInputtype(): string
	{
		return self::INPUTTYPE;
	}
}
