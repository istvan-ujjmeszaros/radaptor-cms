<?php

class FormValidatorSelected extends FormValidator
{
	public function isValid(): bool
	{
		$value = trim((string) $this->_parent->getValue());

		if ($value === '-1' || $value === '') {
			return false;
		}

		return true;
	}
}
