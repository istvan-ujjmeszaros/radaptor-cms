<?php

abstract class FormCustomValidatorTag extends AbstractForm
{
	protected function _validateData(): void
	{
		parent::_validateData();

		$this->validateContext();
		$this->validateName();
	}

	private function validateContext(): void
	{
		$context_input = $this->getInput('context');

		if (!$context_input) {
			return;
		}

		$context = trim((string) $context_input->getValue());

		if ($context === '') {
			return;
		}

		if (!PluginRegistry::hasTagContext($context)) {
			$context_input->addError('Unknown tag context');
		}
	}

	private function validateName(): void
	{
		if ($this->getInput('name')->getValue() == '') {
			return;
		}

		$tag_id = false;

		if ($this->getInput('context')) {
			$context = trim((string) $this->getInput('context')->getValue());

			if (!PluginRegistry::hasTagContext($context)) {
				return;
			}

			$tag_id = EntityTag::getTagId($context, $this->getInput('name')->getValue());
		} elseif ($this->getMode() == self::_MODE_UPDATE) {
			$tag_data = EntityTag::getTagValues($this->getItemId());
			$tag_id = EntityTag::getTagId($tag_data['context'], $this->getInput('name')->getValue());
		}

		if ($tag_id > 0) {
			if ($this->getMode() == self::_MODE_CREATE || ($this->getMode() == self::_MODE_UPDATE && $this->getItemId() != $tag_id)) {
				$this->getInput('name')->addError('This tag already exists');
			}
		}
	}
}
