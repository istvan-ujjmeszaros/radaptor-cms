<?php

declare(strict_types=1);

final class FormDefinitionResolver
{
	public static function resolve(string $form_id): ?FormDefinitionResolution
	{
		$form_id = trim($form_id);

		if ($form_id === '') {
			return null;
		}

		if (FormCaptureDescriptorSchemaValidator::isCaptureSlug($form_id)) {
			return (new FormCaptureDefinitionRepository())->findPublishedResolution($form_id);
		}

		$class_name = FormClassResolver::resolveClassName($form_id);

		if ($class_name === null) {
			return null;
		}

		return FormDefinitionResolution::system($form_id, $class_name);
	}

	public static function requireResolution(string $form_id): FormDefinitionResolution
	{
		$resolution = self::resolve($form_id);

		if ($resolution === null) {
			Kernel::abort("Requested form definition for '{$form_id}' does not exist or is not published.");
		}

		return $resolution;
	}
}
