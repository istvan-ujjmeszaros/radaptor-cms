<?php

class FormValidatorNumber extends FormValidator
{
	public function isValid(): bool
	{
		if ((trim(strip_tags((string) $this->_parent->getValue())) != '') && (!is_numeric($this->_parent->getValue()))) {
			return false;
		}

		return true;
	}
}
