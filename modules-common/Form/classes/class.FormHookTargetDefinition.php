<?php

declare(strict_types=1);

final class FormHookTargetDefinition
{
	/**
	 * @param array<string, mixed> $metadataSchema
	 */
	public function __construct(
		public readonly string $kind,
		public readonly string $nameKey,
		public readonly string $descriptionKey,
		public readonly bool $requiresSystemDeveloper = false,
		public readonly bool $supportsUrl = false,
		public readonly bool $supportsPresetKey = false,
		public readonly bool $supportsSecret = false,
		public readonly array $metadataSchema = [],
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'kind' => $this->kind,
			'name_key' => $this->nameKey,
			'name' => t($this->nameKey),
			'description_key' => $this->descriptionKey,
			'description' => t($this->descriptionKey),
			'requires_system_developer' => $this->requiresSystemDeveloper,
			'supports_url' => $this->supportsUrl,
			'supports_preset_key' => $this->supportsPresetKey,
			'supports_secret' => $this->supportsSecret,
			'metadata_schema' => $this->metadataSchema,
		];
	}
}
