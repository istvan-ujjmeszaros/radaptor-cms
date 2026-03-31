<?php

class FormInputGroupEnd extends FormInput
{
	public const string INPUTTYPE = 'groupend';

	public bool $plainrender = true;
	public bool $save = false;
	public bool $first_in_row = false;
	public bool $last_in_row = false;

	public function getInputtype(): string
	{
		return self::INPUTTYPE;
	}
}
