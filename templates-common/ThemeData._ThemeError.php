<?php

class ThemeData_ThemeError extends AbstractThemeData
{
	public const string ID = 'error';
	public const string SLUG = 'error';
	public const string LIBRARIESCLASSNAME = 'Error';

	public static function getName(): string
	{
		return t('theme.' . self::ID . '.name');
	}

	public static function getDescription(): string
	{
		return t('theme.' . self::ID . '.description');
	}

	public static function getSlug(): string
	{
		return self::SLUG;
	}

	public static function getLibrariesClassName(): string
	{
		return self::LIBRARIESCLASSNAME;
	}

	public static function getListVisibility(): bool
	{
		return false;
	}
}
