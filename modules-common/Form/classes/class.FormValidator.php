<?php

abstract class FormValidator implements iFormValidator
{
	protected FormInput $_parent;

	public function __construct(
		protected string $_errorMsg
	) {
	}

	public function checkParams(): void
	{
	}

	public function setParent(FormInput $parent): void
	{
		$this->_parent = $parent;
	}

	public function getErrorMsg(): string
	{
		return $this->_errorMsg;
	}

	public function validate(): void
	{
		$this->checkParams();

		if ($this->isValid() === false) {
			$this->_parent->addError($this->getErrorMsg());
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toTreeData(): array
	{
		$data = get_object_vars($this);
		unset($data['_parent']);

		return [
			'validator' => preg_replace('/^FormValidator/', '', static::class) ?: static::class,
			'error_message' => $this->getErrorMsg(),
			'props' => $data,
		];
	}
}
