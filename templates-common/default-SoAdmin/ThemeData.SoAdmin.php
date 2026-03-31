<?php

class ThemeDataSoAdmin extends AbstractThemeData
{
	public const string ID = 'so_admin';
	public const string SLUG = 'so-admin';
	public const string LIBRARIESCLASSNAME = 'LibrariesSoAdmin';

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

	public static function getListVisibility(): bool
	{
		return Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER);
	}

	public static function getLibrariesClassName(): string
	{
		return self::LIBRARIESCLASSNAME;
	}
}
