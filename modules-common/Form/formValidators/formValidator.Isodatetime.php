<?php

class FormValidatorIsodatetime extends FormValidator
{
	public function isValid(): bool
	{
		$value = $this->_parent->getValue();

		if (mb_strlen(strval($value)) != 0) {
			// 19 karakter hosszú legyen
			if (mb_strlen((string) $value) == 16) {
				$value .= ':00';
			}

			if (mb_strlen((string) $value) != 19) {
				return false;
			}

			// dátum érvényessége
			$date = mb_substr((string) $value, 0, 10);

			if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $date, $match)) {
				return false;
			}

			if (!checkdate(intval($match[2]), intval($match[3]), intval($match[1]))) {
				return false;
			}

			// elválasztójel szóköz legyen
			$sep = mb_substr((string) $value, 10, 1);

			if ($sep != ' ') {
				return false;
			}

			// idő érvényessége
			$time = mb_substr((string) $value, 11, 8);

			if (!preg_match('/^(([0-1][0-9])|(2[0-3])):[0-5][0-9]:[0-5][0-9]$/D', $time)) {
				return false;
			}
		}

		return true;
	}
}
