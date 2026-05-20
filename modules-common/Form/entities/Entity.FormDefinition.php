<?php

declare(strict_types=1);

/**
 * @phpstan-type ShapeEntityFormDefinition array{
 *     definition_id?: int,
 *     definition_slug: string,
 *     kind?: string,
 *     source?: string,
 *     status?: string,
 *     owner_user_id?: int|null,
 *     security_json?: string,
 *     published_version_id?: int|null,
 * }
 *
 * @property ?int $definition_id
 * @property ?string $definition_slug
 * @property ?string $kind
 * @property ?string $source
 * @property ?string $status
 * @property ?int $owner_user_id
 * @property ?string $security_json
 * @property ?int $published_version_id
 *
 * @extends SQLEntity<ShapeEntityFormDefinition>
 */
class EntityFormDefinition extends SQLEntity
{
	public const string TABLE_NAME = 'form_definitions';

	public static function findBySlug(string $definition_slug): ?self
	{
		return self::findFirst(['definition_slug' => $definition_slug]);
	}

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityFormDefinition $data
	 */
	public function __construct(array $data)
	{
		parent::__construct($data);
	}

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityFormDefinition $data
	 */
	public static function saveFromArray(array $data): static
	{
		return parent::saveFromArray($data);
	}

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityFormDefinition $data
	 */
	public static function createFromArray(array $data): static
	{
		return parent::createFromArray($data);
	}

	/**
	 * {@inheritDoc}
	 * @param int|string|array<string, mixed> $id
	 * @param ShapeEntityFormDefinition $data
	 */
	public static function updateById(int|string|array $id, array $data): static
	{
		return parent::updateById($id, $data);
	}
}
