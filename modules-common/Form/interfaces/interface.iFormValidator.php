<?php

interface iFormValidator
{
	public function setParent(FormInput $parent): void;

	public function isValid(): bool;

	public function validate(): void;

	public function getErrorMsg(): string;

	public function checkParams(): void;
}
