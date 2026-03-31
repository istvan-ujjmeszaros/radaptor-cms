<?php

class FormValidatorIsotime extends FormValidator
{
	public function isValid(): bool
	{
		// Get the value from the parent class
		$value = $this->_parent->getValue();

		// Validate only if the string is not empty
		if (mb_strlen(strval($value)) != 0) {
			// Regular expression to check ISO time format (HH:MM:SS)
			if (!preg_match('/^(([0-1][0-9])|(2[0-3])):[0-5][0-9]:[0-5][0-9]$/D', (string) $value)) {
				return false;
			}
		}

		return true; // Return true if the format is correct or the string is empty
	}
}
