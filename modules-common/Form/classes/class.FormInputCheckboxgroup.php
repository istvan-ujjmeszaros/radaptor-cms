<?php

class FormInputCheckboxgroup extends FormInput
{
	public const string INPUTTYPE = 'checkboxgroup';

	/**
	 * @var array A labeleket tartalmazó tömb (kulcsok nélkül)
	 */
	public array $values = [];

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
