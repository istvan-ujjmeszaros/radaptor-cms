<?php

declare(strict_types=1);

final class ResourceLocaleFormHelper
{
	public static function addLocaleInput(AbstractForm $form, int $resource_id): void
	{
		if (self::isAdminResource(ResourceTreeHandler::getResourceTreeEntryDataById($resource_id))) {
			return;
		}

		$current_locale = LocaleService::tryCanonicalize((string) ($form->initvalues['locale'] ?? ''));
		$enabled_locales = LocaleService::enabledForNewContent();

		if (count($enabled_locales) <= 1 && ($current_locale === null || $current_locale === ($enabled_locales[0] ?? null))) {
			return;
		}

		$locale = new FormInputSelect('locale', $form);
		$locale->label = t('cms.resource.field.locale.label');
		$locale->explanation = t('cms.resource.field.locale.explanation');
		$locale->values = [
			[
				'inputtype' => 'option',
				'value' => '',
				'label' => t('cms.resource.field.locale.dynamic'),
			],
			...LocaleRegistry::buildSelectOptions(LocaleService::allForExistingContentEditing($current_locale)),
		];
	}

	public static function resolveSubmittedLocale(AbstractForm $form, int $resource_id, bool $is_update = false): ?string
	{
		$resource_data = ResourceTreeHandler::getResourceTreeEntryDataById($resource_id);

		if (self::isAdminResource($resource_data)) {
			return null;
		}

		$submitted = $form->savedata['locale'] ?? null;

		if (is_string($submitted)) {
			$submitted = trim($submitted);

			return $submitted !== '' ? LocaleService::canonicalize($submitted) : null;
		}

		if ($is_update) {
			$current_locale = LocaleService::tryCanonicalize((string) ($resource_data['locale'] ?? ''));
			$single_locale = self::getSingleEnabledLocale();

			if ($single_locale !== null && !self::isAdminResource($resource_data)) {
				return $single_locale;
			}

			return $current_locale;
		}

		return self::getSingleEnabledLocale();
	}

	/**
	 * @param array<string, mixed>|null $resource_data
	 */
	private static function isAdminResource(?array $resource_data): bool
	{
		if (!is_array($resource_data)) {
			return false;
		}

		$path = ResourceTreeHandler::buildPathFromNodeData($resource_data);

		return $path === '/admin' || str_starts_with($path, '/admin/');
	}

	private static function getSingleEnabledLocale(): ?string
	{
		$enabled = LocaleService::enabledForNewContent();

		return count($enabled) === 1 ? $enabled[0] : null;
	}
}
