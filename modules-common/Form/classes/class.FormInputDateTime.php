<?php

class FormInputDateTime extends FormInput
{
	public const string INPUTTYPE = 'datetime';

	public function getValue(): mixed
	{
		$value = parent::getValue();

		if (!is_string($value) || trim($value) === '') {
			return $value;
		}

		// Convert only on form submission; leave init/display values untouched.
		if (!array_key_exists($this->id, Request::getPOST())) {
			return $value;
		}

		$normalized = self::normalizeDateTimeValue($value);

		if ($normalized === null) {
			return $value;
		}

		// In update forms, unchanged values may already be UTC from storage.
		// Avoid applying local->UTC conversion again when posted value matches init value.
		$initialValue = $this->getParent()?->initvalues[$this->fieldname] ?? null;
		$initialNormalized = is_string($initialValue)
			? self::normalizeDateTimeValue($initialValue)
			: null;

		if ($initialNormalized !== null && $initialNormalized === $normalized) {
			return $initialNormalized;
		}

		$sourceTimezone = self::resolveInputTimezone();

		$localDateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $normalized, $sourceTimezone);

		if ($localDateTime === false) {
			return $value;
		}

		return $localDateTime
			->setTimezone(new DateTimeZone('UTC'))
			->format('Y-m-d H:i:s');
	}

	public function getInputtype(): string
	{
		return self::INPUTTYPE;
	}

	private static function resolveInputTimezone(): DateTimeZone
	{
		$submittedTimezone = trim((string) Request::_POST('client_timezone', ''));

		if (self::isValidIanaTimezone($submittedTimezone)) {
			return new DateTimeZone($submittedTimezone);
		}

		$userTimezone = trim((string) (User::getCurrentUser()['timezone'] ?? ''));

		if (self::isValidIanaTimezone($userTimezone)) {
			return new DateTimeZone($userTimezone);
		}

		return new DateTimeZone('UTC');
	}

	private static function isValidIanaTimezone(string $timezone): bool
	{
		if ($timezone === '') {
			return false;
		}

		try {
			new DateTimeZone($timezone);
		} catch (Exception) {
			return false;
		}

		return true;
	}

	private static function normalizeDateTimeValue(string $value): ?string
	{
		$normalized = trim($value);

		if (mb_strlen($normalized) === 16) {
			$normalized .= ':00';
		}

		$ok = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $normalized) !== false;

		return $ok ? $normalized : null;
	}
}
