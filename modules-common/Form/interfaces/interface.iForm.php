<?php

interface iForm
{
	public function commit(): void;

	public function setMetadata(): void;

	public function makeInputs(): void;

	public function setInitValues(): void;

	public function hasRole(): bool;

	public static function getDefaultPathForCreation(): array;
}
