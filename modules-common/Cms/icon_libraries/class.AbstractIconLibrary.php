<?php

abstract class AbstractIconLibrary implements iIconLibrary
{
	/** @var array<string, string> Semantic name => library-specific name */
	protected static array $iconMap = [];

	public static function mapToName(IconNames $icon): string
	{
		return static::$iconMap[$icon->value] ?? $icon->value;
	}

	public static function path(IconNames $icon, string $size = 'default'): string
	{
		return static::buildPath(static::mapToName($icon), $size);
	}

	public static function render(IconNames $icon, string $alt = '', string $size = 'default'): string
	{
		return static::buildHtml(static::mapToName($icon), $alt, $size);
	}

	abstract protected static function buildPath(string $name, string $size): string;

	abstract protected static function buildHtml(string $name, string $alt, string $size): string;
}
