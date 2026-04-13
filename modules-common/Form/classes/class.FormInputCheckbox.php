<?php

class FormInputCheckbox extends FormInput
{
	public const string INPUTTYPE = 'checkbox';

	public function getInputtype(): string
	{
		return self::INPUTTYPE;
	}

	public function getCheckboxValue($key)
	{
		$value = $this->getValue();

		return $value[$key] ?? null;
	}
}
