<?php

abstract class FormCustomValidatorRichText extends AbstractForm
{
	protected function _validateData(): void
	{
		parent::_validateData();

		$this->validateName();
	}

	private function validateName(): void
	{
		$locale_input = $this->getInput('locale');
		$locale_modified = $locale_input !== null && $this->isModified('locale');

		if (!$this->isModified('name') && !$locale_modified) {
			return;
		}

		$locale = $this->resolveValidationLocale($locale_input);
		$id = EntityRichtext::getContentIdByName($this->getInput('name')->getValue(), $locale);

		if ($id == EntityRichtext::ERROR_NOT_FOUND) {
			return;
		}

		if ($id !== $this->getItemId()) {
			$this->getInput('name')->addError(t('cms.richtext.field.name.unique_error'));
		}
	}

	private function resolveValidationLocale(?FormInput $locale_input): string
	{
		if ($locale_input !== null && trim((string) $locale_input->getValue()) !== '') {
			return LocaleService::canonicalize((string) $locale_input->getValue());
		}

		$current_locale = LocaleService::tryCanonicalize((string) ($this->initvalues['locale'] ?? ''));

		if ($current_locale !== null) {
			return $current_locale;
		}

		$item_id = $this->getItemId();

		if ($item_id > 0) {
			$saved_locale = EntityRichtext::getContentLocale($item_id);

			if ($saved_locale !== null) {
				return $saved_locale;
			}
		}

		return RichTextLocaleService::getLocaleForCurrentRequest();
	}
}
