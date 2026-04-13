<?php

/**
 * $return[] = array(
 *     'inputtype' => 'option',
 *     'value' => $value,
 *     'label' => $label,
 * );.
 */
class FormInputSelect extends FormInput
{
	public const string INPUTTYPE = 'select';

	public array $values = [];
	public bool $required = true;

	public function getInputtype(): string
	{
		return self::INPUTTYPE;
	}
}
