<?php

class FormInputLinkGroup extends FormInput
{
	public const string INPUTTYPE = 'linkgroup';

	public bool $save = false;

	/**
	 * @var list<array{url: string, label: string, active?: bool}>
	 */
	public array $values = [];

	public function getInputtype(): string
	{
		return self::INPUTTYPE;
	}
}
