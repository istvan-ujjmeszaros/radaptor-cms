<?php

class ThemeBase
{
	public const string _RESOURCENAME = '_theme_settings';

	public static function getClass(iTreeBuildContext $tree_build_context, mixed $connection_id): string
	{
		$settings = WidgetSettings::getSettings($connection_id);

		if (!isset($settings['widget_width'])) {
			return '';
		}

		$theme_name = Themes::getThemeNameForUser($tree_build_context->getLayoutTypeName());

		$return = self::getThemeDataClassForWidth($theme_name, $settings['widget_width']);

		if ($settings['is_last']) {
			$return .= ' ' . Themes::factory($theme_name)->getLastClass();
		}

		return $return;
	}

	public static function getThemeDataClassForWidth(string $theme_name, mixed $width): string
	{
		$theme = self::factory($theme_name);

		$width_possibilities = $theme->getWidthPossibilities();

		return $width_possibilities[$width]['class'] ?? '';
	}

	public static function saveSettings(array $savedata): int
	{
		return AttributeHandler::addAttribute(new AttributeResourceIdentifier(self::_RESOURCENAME), $savedata);
	}

	public static function getSettings(): array
	{
		return AttributeHandler::getAttributes(new AttributeResourceIdentifier(self::_RESOURCENAME));
	}

	public static function getThemeNameForLayout($layout_name): string
	{
		if (!is_string($layout_name) || $layout_name === '') {
			return '';
		}

		$data = AttributeHandler::getAttributeArray(new AttributeResourceIdentifier(self::_RESOURCENAME), [$layout_name]);

		return $data[$layout_name] ?? '';
	}

	public static function factory(string $themeDataName): AbstractThemeData
	{
		$theme_classname = 'ThemeData' . ucwords($themeDataName);

		try {
			if (AutoloaderFromGeneratedMap::autoloaderClassExists($theme_classname)) {
				$themeClass = new $theme_classname();
			} else {
				$themeClass = new ThemeData_ThemeError();
			}
		} catch (Exception) {
			$themeClass = new ThemeData_ThemeError();
		}

		if (!$themeClass instanceof AbstractThemeData) {
			Kernel::abort("Theme <i>$themeDataName</i> must implement <b>AbstractThemeData</b>!");
		}

		return $themeClass;
	}

	public static function getWidthValuesForSelect(mixed $connection_id): array
	{
		$connectionData = Widget::getConnectionData($connection_id);

		$resource = ResourceTypeFactory::Factory($connectionData['page_id']);

		if ($resource instanceof ResourceTypeWebpage) {
			$layout_name = $resource->getView()->getLayoutTypeName();
			$theme_name = Themes::getThemeNameForUser($layout_name);

			$values = Themes::factory($theme_name)->getWidthPossibilities();
		} else {
			$values = [];
		}

		return array_map(fn ($key, $value) => [
			'inputtype' => 'option',
			'value' => $key,
			'label' => $value['label'],
		], array_keys($values), $values);
	}
}
