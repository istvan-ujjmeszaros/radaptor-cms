<?php

declare(strict_types=1);

/**
 * @phpstan-type ShapeEntityFormHookDelivery array{
 *     delivery_id?: int,
 *     hook_id?: int|null,
 *     definition_id: int,
 *     version_id: int,
 *     submission_id: int,
 *     target_kind: string,
 *     target_label: string,
 *     status?: string,
 *     environment: string,
 *     payload_json?: string|null,
 *     result_json?: string|null,
 *     error_code?: string|null,
 *     error_message?: string|null,
 *     queued_at?: string|null,
 *     completed_at?: string|null,
 *     created_at?: string|null,
 *     updated_at?: string|null,
 * }
 *
 * @property ?int $delivery_id
 * @property ?int $hook_id
 * @property ?int $definition_id
 * @property ?int $version_id
 * @property ?int $submission_id
 * @property ?string $target_kind
 * @property ?string $target_label
 * @property ?string $status
 * @property ?string $environment
 * @property ?string $payload_json
 * @property ?string $result_json
 * @property ?string $error_code
 * @property ?string $error_message
 * @property ?string $queued_at
 * @property ?string $completed_at
 * @property ?string $created_at
 * @property ?string $updated_at
 *
 * @extends SQLEntity<ShapeEntityFormHookDelivery>
 */
class EntityFormHookDelivery extends SQLEntity
{
	public const string TABLE_NAME = 'form_hook_deliveries';

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityFormHookDelivery $data
	 */
	public function __construct(array $data)
	{
		parent::__construct($data);
	}

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityFormHookDelivery $data
	 */
	public static function saveFromArray(array $data): static
	{
		return parent::saveFromArray($data);
	}

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityFormHookDelivery $data
	 */
	public static function createFromArray(array $data): static
	{
		return parent::createFromArray($data);
	}

	/**
	 * {@inheritDoc}
	 * @param int|string|array<string, mixed> $id
	 * @param ShapeEntityFormHookDelivery $data
	 */
	public static function updateById(int|string|array $id, array $data): static
	{
		return parent::updateById($id, $data);
	}
}
