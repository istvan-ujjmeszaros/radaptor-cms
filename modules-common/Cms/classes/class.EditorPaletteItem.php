<?php

declare(strict_types=1);

final class EditorPaletteItem
{
	/**
	 * @param list<string> $dropTargetIds
	 * @param array<string, mixed> $defaults
	 */
	public function __construct(
		public readonly string $type,
		public readonly string $label,
		public readonly string $icon,
		public readonly array $dropTargetIds,
		public readonly array $defaults = [],
	) {
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
			'drop_target_ids' => $this->dropTargetIds,
			'defaults' => $this->defaults,
		];
	}
}
