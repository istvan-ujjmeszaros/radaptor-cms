<?php

interface iIconLibrary
{
	public static function render(IconNames $icon, string $alt = '', string $size = 'default'): string;

	public static function path(IconNames $icon, string $size = 'default'): string;

	public static function mapToName(IconNames $icon): string;
}
