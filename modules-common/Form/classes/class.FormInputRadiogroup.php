<?php

class FormInputRadiogroup extends FormInput
{
	public const string INPUTTYPE = 'radiogroup';

	public array $values = [];

	public function getInputtype(): string
	{
		return self::INPUTTYPE;
	}
}
