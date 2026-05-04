<?php

class Layout extends LayoutTypes
{
	public static function checkLayoutExists(string $layoutTypeName): bool
	{
		return in_array($layoutTypeName, self::$_layoutTypeNames);
	}

	/**
	 * @return list<string>
	 */
	public static function getRegisteredLayoutTypes(): array
	{
		return array_values(self::$_layoutTypeNames);
	}

	/**
	 * Get the class name for a layout type without instantiating it.
	 *
	 * @param string $layoutTypeName The layout type name (e.g., 'public_default')
	 * @return class-string<AbstractLayoutType> The fully qualified class name
	 */
	public static function getLayoutClassName(string $layoutTypeName): string
	{
		$layoutTypeName = str_replace('_', ' ', $layoutTypeName);

		$layout_classname = 'LayoutType' . str_replace(' ', '', ucwords($layoutTypeName));

		if ($layout_classname === 'LayoutType') {
			$layout_classname = 'LayoutTypeUnknown';
		}

		return $layout_classname;
	}

	public static function factory(string $layoutTypeName): AbstractLayoutType
	{
		$layout_classname = self::getLayoutClassName($layoutTypeName);

		$layoutClass = new $layout_classname();

		return $layoutClass;
	}

	public static function getLayoutListForSelect(): array
	{
		$return = [];

		foreach (self::$_layoutTypeNames as $layoutTypeName) {
			$layout = self::factory($layoutTypeName);

			if ($layout->getListVisibility()) {
				$return[] = [
					'inputtype' => 'option',
					'value' => $layoutTypeName,
					'label' => $layout->getName(),
				];
			}
		}

		return $return;
	}

	public static function getRenderMode($layout)
	{
		if (isset(self::$_layoutTypeNames[$layout]['render_mode'])) {
			return self::$_layoutTypeNames[$layout]['render_mode'];
		} elseif (isset(self::$_layoutTypeNames[$layout])) {
			Kernel::abort("Unregistered render_mode for layout: <i>$layout</i>");
		} else {
			Kernel::abort("Unregistered layout: <i>$layout</i>");
		}
	}
}
