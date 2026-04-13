<?php

class FormValidatorInteger extends FormValidator
{
	public function isValid(): bool
	{
		if (!is_numeric($this->_parent->getValue()) || intval($this->_parent->getValue()) != $this->_parent->getValue()) {
			return false;
		}

		return true;
	}
}
