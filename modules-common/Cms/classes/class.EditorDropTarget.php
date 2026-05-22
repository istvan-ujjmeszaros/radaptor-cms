<?php

declare(strict_types=1);

final class EditorDropTarget
{
	/**
	 * @param list<string> $acceptedTypes
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $label,
		public readonly array $acceptedTypes,
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->id,
			'label' => $this->label,
			'accepted_types' => $this->acceptedTypes,
		];
	}
}
