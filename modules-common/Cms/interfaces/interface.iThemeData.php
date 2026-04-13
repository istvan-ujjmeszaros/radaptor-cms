<?php

interface iThemeData
{
	public static function initialize(): void;

	public static function getLibrariesClassName(): string;

	public static function getIconLibrary(): string;

	public static function getWidthPossibilities(): array;

	public static function getFirstClass(): string;

	public static function getLastClass(): string;

	public static function extraWebpageFormInputs(FormTypeWebpagePage $form): array;

	public static function extraAttributes(FormTypeWebpagePage $form): array;

	/**
	 * Get URL-safe slug for the theme (e.g., "radaptor-portal-admin").
	 */
	public static function getSlug(): string;

	/**
	 * Get stable class-based theme identifier (e.g., "SoAdmin", "RadaptorPortalAdmin").
	 * Used for template key resolution. Must not be translated.
	 */
	public static function getThemeName(): string;
}
