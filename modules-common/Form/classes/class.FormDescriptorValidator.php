<?php

declare(strict_types=1);

final class FormDescriptorValidator extends FormValidator
{
	/**
	 * @param array<string, mixed> $options
	 */
	public function __construct(
		private readonly string $type,
		string $errorMsg,
		private readonly array $options = [],
	) {
		parent::__construct($errorMsg);
	}

	public function isValid(): bool
	{
		return FormDescriptorValidatorRegistry::isValid($this->type, $this->_parent->getValue(), $this->options);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toTreeData(): array
	{
		return [
			'validator' => $this->type,
			'type' => $this->type,
			'error_message' => $this->getErrorMsg(),
			'props' => $this->options,
		];
	}
}
