<?php

class FormValidatorRange extends FormValidator
{
	public ?int $min = null;
	public ?int $max = null;

	public function checkParams(): void
	{
		if (is_null($this->min)) {
			Kernel::abort('Property <i>min</i> must be set for validator ' . self::class);
		}

		if (is_null($this->max)) {
			Kernel::abort('Property <i>max</i> must be set for validator ' . self::class);
		}
	}

	public function isValid(): bool
	{
		if ((trim(strip_tags((string) $this->_parent->getValue())) != '') && (!is_numeric($this->_parent->getValue()))) {
			return false;
		}

		if ($this->_parent->getValue() < $this->min || $this->_parent->getValue() > $this->max) {
			return false;
		}

		return true;
	}
}
