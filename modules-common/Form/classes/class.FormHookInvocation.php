<?php

declare(strict_types=1);

final class FormHookInvocation
{
	/**
	 * @param array<string, mixed> $payload
	 * @param array<string, mixed> $metadata
	 */
	public function __construct(
		public readonly EntityFormHookTarget $hook,
		public readonly FormDefinitionResolution $resolution,
		public readonly int $submissionId,
		public readonly int $deliveryId,
		public readonly string $environment,
		public readonly array $payload,
		public readonly array $metadata,
	) {
	}

	public function hookId(): int
	{
		return (int)$this->hook->hook_id;
	}

	public function targetKind(): string
	{
		return (string)$this->hook->target_kind;
	}
}
