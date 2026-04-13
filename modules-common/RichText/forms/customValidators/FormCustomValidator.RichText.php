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
		if (!$this->isModified('name')) {
			return;
		}

		$id = EntityRichtext::getContentIdByName($this->getInput('name')->getValue());

		if ($id == EntityRichtext::ERROR_NOT_FOUND) {
			return;
		}

		if ($id !== $this->getItemId()) {
			$this->getInput('name')->addError(t('cms.richtext.field.name.unique_error'));
		}
	}
}
