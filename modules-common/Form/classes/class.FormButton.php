<?php

class FormButton
{
	public const string CLASS_STANDARD = 'standard';
	public const string CLASS_POSITIVE = 'positive';
	public const string CLASS_NEGATIVE = 'negative';
	public const string CLASS_REGULAR = 'regular';

	public function __construct(public $text, public ?IconNames $icon = null, public $class = null)
	{
	}
}
