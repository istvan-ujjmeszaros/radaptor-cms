<?php

declare(strict_types=1);

final class FormClassResolver
{
	/**
	 * @return class-string<AbstractForm>|null
	 */
	public static function resolveClassName(string $form_id): ?string
	{
		$form_id = trim($form_id);

		if ($form_id === '') {
			return null;
		}

		$candidates = [
			'FormType' . $form_id,
			'FormType' . ucwords($form_id),
		];

		foreach (array_unique($candidates) as $class_name) {
			if (!AutoloaderFromGeneratedMap::autoloaderClassExists($class_name) && !class_exists($class_name)) {
				continue;
			}

			if (is_subclass_of($class_name, AbstractForm::class)) {
				return $class_name;
			}
		}

		return null;
	}

	/**
	 * @return class-string<AbstractForm>
	 */
	public static function requireClassName(string $form_id): string
	{
		$class_name = self::resolveClassName($form_id);

		if ($class_name === null) {
			Kernel::abort("Requested form class for '{$form_id}' does not exist or does not implement AbstractForm.");
		}

		return $class_name;
	}
}
