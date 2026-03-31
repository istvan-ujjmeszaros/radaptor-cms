<?php

abstract class AbstractThemeData extends Themes implements iThemeData, iListable
{
	public static function initialize(): void
	{
	}

	public static function getIconLibrary(): string
	{
		return 'Carbon';
	}

	public static function extraWebpageFormInputs(FormTypeWebpagePage $form): array
	{
		return [];
	}

	public static function extraAttributes(FormTypeWebpagePage $form): array
	{
		return [];
	}

	public static function getWidthPossibilities(): array
	{
		return [
			'full' => [
				'class' => 'grid_12',
				'label' => 'full',
			],
			'three_fourth' => [
				'class' => 'grid_9',
				'label' => 'three fourth',
			],
			'two_third' => [
				'class' => 'grid_8',
				'label' => 'two thirds',
			],
			'half' => [
				'class' => 'grid_6',
				'label' => 'half',
			],
			'third' => [
				'class' => 'grid_4',
				'label' => 'third',
			],
			'fourth' => [
				'class' => 'grid_3',
				'label' => 'fourth',
			],
		];
	}

	public static function getFirstClass(): string
	{
		return '';
	}

	public static function getLastClass(): string
	{
		return '';
	}

	/**
	 * Default slug derivation from theme name.
	 * Converts PascalCase name to kebab-case.
	 * Example: RadaptorPortalAdmin → radaptor-portal-admin.
	 */
	public static function getSlug(): string
	{
		return strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1-$2', static::getName()));
	}
}
