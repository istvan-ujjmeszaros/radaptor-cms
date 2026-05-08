<?php

declare(strict_types=1);

final class RichTextWidgetContentLocaleStrategy extends WidgetContentLocaleStrategy
{
	/**
	 * @param array<string, scalar|null> $attributes
	 */
	public function assertConnectionAttributesCompatible(int $connection_id, array $attributes): void
	{
		// RichText locale is authoring metadata only. Existing content remains renderable
		// on any page locale; selector filtering is just an editor convenience.
		unset($connection_id, $attributes);
	}
}
