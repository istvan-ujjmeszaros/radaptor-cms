<?php

declare(strict_types=1);

final class FormEditorFieldCommandContext
{
	/**
	 * @param array<string, mixed> $field
	 * @param array<string, mixed> $target
	 */
	public function __construct(
		public readonly string $formId,
		public readonly string $definitionSlug,
		public readonly int $hostPageId,
		public readonly int $widgetConnectionId,
		public readonly string $fieldUid,
		public readonly string $fieldKey,
		public readonly int $fieldIndex,
		public readonly int $visibleFieldCount,
		public readonly string $panelId,
		public readonly array $field,
		public readonly array $target,
	) {
	}
}
