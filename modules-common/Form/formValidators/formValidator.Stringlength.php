<?php

class FormValidatorStringlength extends FormValidator
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
		if (
			(
				mb_strlen(trim(mb_convert_encoding($this->_parent->getValue(), 'ISO-8859-1'))) < $this->min
			)
			|| (
				mb_strlen(trim(mb_convert_encoding($this->_parent->getValue(), 'ISO-8859-1'))) > $this->max
			)
		) {
			return false;
		}

		return true;
	}

	public function getErrorMsg(): string
	{
		return $this->_errorMsg . ' (' . mb_strlen((string) $this->_parent->getValue()) . ')';
	}
}
