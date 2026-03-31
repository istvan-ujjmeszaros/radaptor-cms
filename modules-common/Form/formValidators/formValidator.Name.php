<?php

class FormValidatorName extends FormValidator
{
	public function isValid(): bool
	{
		if (!preg_match("/^([a-zA-Z\x80-\xff])+([\.a-zA-Z\x80-\xff0-9 _-])+([\.a-zA-Z\x80-\xff0-9 _-])$/", (string) $this->_parent->getValue())) {
			return false;
		}

		return true;
	}
}
