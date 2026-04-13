<?php

class FormValidatorIsodate extends FormValidator
{
	public function isValid(): bool
	{
		if (mb_strlen(strval($this->_parent->getValue())) != 0) {
			if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', (string) $this->_parent->getValue(), $match)) {
				return false;
			} else {
				if (!checkdate(intval($match[2]), intval($match[3]), intval($match[1]))) {
					return false;
				}
			}
		}

		return true;
	}
}
