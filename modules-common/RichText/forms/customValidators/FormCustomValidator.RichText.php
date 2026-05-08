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

		$locale = $locale_input !== null && trim((string) $locale_input->getValue()) !== ''
			? LocaleService::canonicalize((string) $locale_input->getValue())
			: RichTextLocaleService::getLocaleForCurrentRequest();
		$id = EntityRichtext::getContentIdByName($this->getInput('name')->getValue(), $locale);

		if ($id == EntityRichtext::ERROR_NOT_FOUND) {
			return;
		}

		if ($id !== $this->getItemId()) {
			$this->getInput('name')->addError(t('cms.richtext.field.name.unique_error'));
		}
	}
}
