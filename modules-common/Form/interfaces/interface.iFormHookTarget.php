<?php

declare(strict_types=1);

interface iFormHookTarget
{
	public function definition(): FormHookTargetDefinition;

	/**
	 * @param array<string, mixed> $config
	 * @param list<string> $field_keys
	 */
	public function validateConfig(array $config, array $field_keys, bool $is_system_developer): void;

	public function invoke(FormHookInvocation $invocation): FormHookResult;
}
