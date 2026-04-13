<?php

class FormInputDate extends FormInput
{
	public const string INPUTTYPE = 'date';

	public function getInputtype(): string
	{
		return self::INPUTTYPE;
	}
}
