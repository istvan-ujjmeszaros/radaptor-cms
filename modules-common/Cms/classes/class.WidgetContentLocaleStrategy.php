<?php

declare(strict_types=1);

abstract class WidgetContentLocaleStrategy
{
	/**
	 * @param array<string, scalar|null> $attributes
	 */
	abstract public function assertConnectionAttributesCompatible(int $connection_id, array $attributes): void;
}
