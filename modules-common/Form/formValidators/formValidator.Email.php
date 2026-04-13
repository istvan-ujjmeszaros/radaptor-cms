<?php

class FormValidatorEmail extends FormValidator
{
	public function isValid(): bool
	{
		if (mb_strlen(strval($this->_parent->getValue())) != 0) {
			if (!preg_match("/^([a-zA-Z0-9])+([\.a-zA-Z0-9_-])*@([a-zA-Z0-9_-])+(\.[a-zA-Z0-9_-]+)*\.([a-zA-Z]{2,6})$/", (string) $this->_parent->getValue())) {
				return false;
			}
		}

		return true;
	}
}
