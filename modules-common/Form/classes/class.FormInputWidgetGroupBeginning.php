<?php

// TODO: Figure out when do we use that form
class FormInputWidgetGroupBeginning extends FormInput
{
	public const string INPUTTYPE = 'widgetgroupbeginning';

	public string $class = 'input-widget-group';
	public bool $save = false;
	public bool $first_in_row = false;
	public bool $last_in_row = false;

	public function getInputtype(): string
	{
		return self::INPUTTYPE;
	}
}
