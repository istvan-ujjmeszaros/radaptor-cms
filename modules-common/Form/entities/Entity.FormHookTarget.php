<?php

declare(strict_types=1);

/**
 * @phpstan-type ShapeEntityFormHookTarget array{
 *     hook_id?: int,
 *     definition_id: int,
 *     target_kind: string,
 *     enabled?: int|bool,
 *     label: string,
 *     url?: string|null,
 *     preset_key?: string|null,
 *     metadata_json: string,
 *     excluded_field_keys_json: string,
 *     enable_in_non_production?: int|bool,
 *     secret_ciphertext?: string|null,
 *     secret_nonce?: string|null,
 *     secret_tag?: string|null,
 *     created_by_user_id?: int|null,
 *     updated_by_user_id?: int|null,
 * }
 *
 * @property ?int $hook_id
 * @property ?int $definition_id
 * @property ?string $target_kind
 * @property int|bool|null $enabled
 * @property ?string $label
 * @property ?string $url
 * @property ?string $preset_key
 * @property ?string $metadata_json
 * @property ?string $excluded_field_keys_json
 * @property int|bool|null $enable_in_non_production
 * @property ?string $secret_ciphertext
 * @property ?string $secret_nonce
 * @property ?string $secret_tag
 * @property ?int $created_by_user_id
 * @property ?int $updated_by_user_id
 *
 * @extends SQLEntity<ShapeEntityFormHookTarget>
 */
class EntityFormHookTarget extends SQLEntity
{
	public const string TABLE_NAME = 'form_hook_targets';

	/**
	 * @return list<self>
	 */
	public static function findEnabledForDefinition(int $definition_id): array
	{
		return self::findMany(['definition_id' => $definition_id, 'enabled' => 1], 'hook_id ASC');
	}

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityFormHookTarget $data
	 */
	public function __construct(array $data)
	{
		parent::__construct($data);
	}

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityFormHookTarget $data
	 */
	public static function saveFromArray(array $data): static
	{
		return parent::saveFromArray($data);
	}

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityFormHookTarget $data
	 */
	public static function createFromArray(array $data): static
	{
		return parent::createFromArray($data);
	}

	/**
	 * {@inheritDoc}
	 * @param int|string|array<string, mixed> $id
	 * @param ShapeEntityFormHookTarget $data
	 */
	public static function updateById(int|string|array $id, array $data): static
	{
		return parent::updateById($id, $data);
	}
}
