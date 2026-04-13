<?php

class Icons
{
	/**
	 * Maps runtime string identifiers to IconNames.
	 * @var array<string, IconNames>
	 */
	private const array STRING_TO_ICON_MAP = [
		// Resource tree node types
		'root' => IconNames::MENUBAR,
		'folder' => IconNames::FOLDER,
		'webpage' => IconNames::LINK,
		'file' => IconNames::DATASHEET,

		// Catcher variants
		'root-catcher' => IconNames::MENUBAR,
		'folder-catcher' => IconNames::FOLDER,
		'webpage-catcher' => IconNames::LINK,
		'file-catcher' => IconNames::DATASHEET,
	];

	// Transparent 1x1 PNG as data URI
	private const string TRANSPARENT_PIXEL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

	/**
	 * Returns an invisible placeholder img tag for layout purposes.
	 */
	public static function placeholder(int $width = 16, int $height = 16): string
	{
		return "<img src=\"" . self::TRANSPARENT_PIXEL . "\" width=\"{$width}\" height=\"{$height}\" alt=\"\" />";
	}

	/**
	 * Resolve a string identifier to an icon path.
	 */
	public static function resolve(string $identifier, string $size = 'default'): string
	{
		if ($identifier === '') {
			return '';
		}

		$icon = self::STRING_TO_ICON_MAP[$identifier] ?? null;

		if ($icon === null) {
			return self::TRANSPARENT_PIXEL;
		}

		return self::path($icon, $size);
	}

	public static function get(?IconNames $icon, string $alt = '', string $size = 'default'): string
	{
		if ($icon === null) {
			return '';
		}

		return self::getLibrary()::render($icon, $alt, $size);
	}

	public static function path(?IconNames $icon, string $size = 'default'): string
	{
		if ($icon === null) {
			return '';
		}

		return self::getLibrary()::path($icon, $size);
	}

	/**
	 * @return class-string<iIconLibrary>
	 */
	private static function getLibrary(): string
	{
		$themeName = Config::APP_DEFAULT_THEME_NAME->value();
		$libraryName = 'Tabler'; // default

		if ($themeName) {
			$theme = Themes::factory($themeName);
			$libraryName = $theme->getIconLibrary();
		}

		return 'IconLibrary' . $libraryName;
	}
}
