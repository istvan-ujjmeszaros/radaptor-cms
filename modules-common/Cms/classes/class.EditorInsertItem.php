<?php

declare(strict_types=1);

final class EditorInsertItem
{
	/**
	 * @param array<string, mixed> $defaults
	 */
	public function __construct(
		public readonly string $type,
		public readonly string $label,
		public readonly string $icon = '',
		public readonly array $defaults = [],
	) {
	}

	public static function fromPaletteItem(EditorPaletteItem $item): self
	{
		return new self($item->type, $item->label, $item->icon, $item->defaults);
	}

	/**
	 * @param array{type_name:string, name:string, description?:string} $metadata
	 */
	public static function fromWidgetMetadata(array $metadata): self
	{
		$type = (string)$metadata['type_name'];

		return new self(
			$type,
			(string)($metadata['name'] ?? $type),
			'',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'type' => $this->type,
			'label' => $this->label,
			'icon' => $this->icon,
			'defaults' => $this->defaults,
		];
	}
}
