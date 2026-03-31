<?php

class CmsThemeAssetHelper
{
	public static function getThemeAssetsBase(string $theme_slug): string
	{
		return "/assets/packages/themes/{$theme_slug}";
	}

	public static function getStimulusAssetsBase(string $theme_slug): string
	{
		foreach ([
			"/assets/packages/{$theme_slug}",
			"/assets/packages/themes/{$theme_slug}",
		] as $candidate) {
			$base_dir = DEPLOY_ROOT . 'public/www' . $candidate;

			if (is_dir($base_dir . '/controllers') || is_dir($base_dir . '/js')) {
				return $candidate;
			}
		}

		return "/assets/packages/{$theme_slug}";
	}
}
