<?php

declare(strict_types=1);

/**
 * @phpstan-type ShapeEntityFormDefinitionVersion array{
 *     version_id?: int,
 *     definition_id: int,
 *     version_number: int,
 *     status?: string,
 *     descriptor_json: string,
 *     descriptor_hash: string,
 *     created_at?: string|null,
 *     published_at?: string|null,
 * }
 *
 * @property ?int $version_id
 * @property ?int $definition_id
 * @property ?int $version_number
 * @property ?string $status
 * @property ?string $descriptor_json
 * @property ?string $descriptor_hash
 * @property ?string $created_at
 * @property ?string $published_at
 *
 * @extends SQLEntity<ShapeEntityFormDefinitionVersion>
 */
class EntityFormDefinitionVersion extends SQLEntity
{
	public const string TABLE_NAME = 'form_definition_versions';

	public static function findPublishedForDefinition(int $definition_id, int $version_id): ?self
	{
		return self::findFirst([
			'definition_id' => $definition_id,
			'version_id' => $version_id,
			'status' => 'published',
		]);
	}

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityFormDefinitionVersion $data
	 */
	public function __construct(array $data)
	{
		parent::__construct($data);
	}

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityFormDefinitionVersion $data
	 */
	public static function saveFromArray(array $data): static
	{
		return parent::saveFromArray($data);
	}

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityFormDefinitionVersion $data
	 */
	public static function createFromArray(array $data): static
	{
		return parent::createFromArray($data);
	}

	/**
	 * {@inheritDoc}
	 * @param int|string|array<string, mixed> $id
	 * @param ShapeEntityFormDefinitionVersion $data
	 */
	public static function updateById(int|string|array $id, array $data): static
	{
		return parent::updateById($id, $data);
	}
}
