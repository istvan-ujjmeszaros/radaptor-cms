<?php

declare(strict_types=1);

final class FormDefinitionResolver
{
	public static function resolve(string $form_id, ?int $form_definition_version_id = null): ?FormDefinitionResolution
	{
		$form_id = trim($form_id);

		if ($form_id === '') {
			return null;
		}

		if (FormCaptureDescriptorSchemaValidator::isCaptureSlug($form_id)) {
			return (new FormCaptureDefinitionRepository())->findPublishedResolution($form_id, $form_definition_version_id);
		}

		$class_name = FormClassResolver::resolveClassName($form_id);

		if ($class_name === null) {
			return null;
		}

		return FormDefinitionResolution::system($form_id, $class_name);
	}

	/**
	 * @param array<string, mixed> $render_context
	 */
	public static function resolveForRender(string $form_id, array $render_context = []): ?FormDefinitionResolution
	{
		$form_id = trim($form_id);

		if ($form_id === '') {
			return null;
		}

		if (!FormCaptureDescriptorSchemaValidator::isCaptureSlug($form_id)) {
			return self::resolve($form_id);
		}

		$repository = new FormCaptureDefinitionRepository();

		if (!empty($render_context['structure_editable'])) {
			$editable_resolution = $repository->findEditableResolution($form_id);

			if ($editable_resolution instanceof FormDefinitionResolution) {
				return $editable_resolution;
			}
		}

		return $repository->findPublishedResolution($form_id);
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
