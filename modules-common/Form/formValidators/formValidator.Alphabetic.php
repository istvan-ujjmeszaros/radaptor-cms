<?php

class FormValidatorAlphabetic extends FormValidator
{
	public function isValid(): bool
	{
		if (!preg_match("/^[a-zA-Z\x80-\xff]+$/", (string) $this->_parent->getValue())) {
			return false;
		}

		return true;
	}
}
