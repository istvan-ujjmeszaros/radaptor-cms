<?php

class FormValidatorUsername extends FormValidator
{
	public function isValid(): bool
	{
		if (!preg_match("/^([a-zA-Z\x80-\xff])+([\.a-zA-Z\x80-\xff0-9_-])+([\.a-zA-Z\x80-\xff0-9_-])$/", (string) $this->_parent->getValue())) {
			return false;
		}

		return true;
	}
}
