<?php

class structFormSelectOption extends Struct
{
	public function __construct(
		public string $value,
		public string $label,
		public string $inputtype = 'option',
	) {
	}
}
