<?php

class FormValidatorAlphanumeric extends FormValidator
{
	public function isValid(): bool
	{
		if (!preg_match("/^[a-zA-Z0-9\x80-\xff]+$/", (string) $this->_parent->getValue())) {
			return false;
		}

		return true;
	}
}
