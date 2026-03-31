<?php

/**
 * Validates that a value is a valid URL slug.
 *
 * Valid slug format:
 * - Lowercase letters (a-z)
 * - Numbers (0-9)
 * - Hyphens (-)
 * - No leading, trailing, or consecutive hyphens
 *
 * Examples:
 * - Valid: "hello-world", "blog-post-123", "a1b2c3"
 * - Invalid: "Hello-World" (uppercase), "hello--world" (consecutive hyphens),
 *            "-hello" (leading hyphen), "hello-" (trailing hyphen), "hello world" (space)
 */
class FormValidatorSlug extends FormValidator
{
	public function isValid(): bool
	{
		$value = strval($this->_parent->getValue());

		if (mb_strlen($value) == 0) {
			// Empty allowed, use NotEmpty validator separately if required
			return true;
		}

		// Lowercase letters, numbers, hyphens (no leading/trailing/consecutive hyphens)
		return (bool) preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $value);
	}
}
