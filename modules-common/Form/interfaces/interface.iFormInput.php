<?php

interface iFormInput
{
	public function getInputtype(): string;

	/**
	 * @return array<string, mixed>
	 */
	public function buildTree(): array;
}
