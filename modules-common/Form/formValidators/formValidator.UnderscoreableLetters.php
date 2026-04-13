<?php

class FormValidatorUnderscoreableLetters extends FormValidator
{
	public function isValid(): bool
	{
		if (!preg_match("/^[a-zA-Z_]+$/", (string) $this->_parent->getValue())) {
			return false;
		}

		return true;
	}
}
