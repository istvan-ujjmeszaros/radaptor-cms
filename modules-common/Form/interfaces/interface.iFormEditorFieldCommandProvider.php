<?php

declare(strict_types=1);

interface iFormEditorFieldCommandProvider
{
	/**
	 * @return list<FormEditorFieldCommand>
	 */
	public function getCommands(FormEditorFieldCommandContext $context): array;

	/**
	 * @return array<string, string>
	 */
	public function getStrings(): array;
}
