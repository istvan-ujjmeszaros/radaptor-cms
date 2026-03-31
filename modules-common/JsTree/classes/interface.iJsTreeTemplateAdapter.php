<?php

declare(strict_types=1);

interface iJsTreeTemplateAdapter
{
	public function getTemplate(): string;

	/**
	 * @param array<string, mixed> $context
	 * @return array
	 */
	public function build(string $tree_type, array $raw_data, array $context): array;
}
