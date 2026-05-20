<?php

declare(strict_types=1);

/**
 * @phpstan-type ShapeEntityFormSubmission array{
 *     submission_id?: int,
 *     definition_id: int,
 *     version_id: int,
 *     definition_slug: string,
 *     payload_json: string,
 *     user_id?: int|null,
 *     locale?: string|null,
 *     ip_hash?: string|null,
 *     user_agent_hash?: string|null,
 *     host_page_id?: int|null,
 *     widget_connection_id?: int|null,
 * }
 *
 * @property ?int $submission_id
 * @property ?int $definition_id
 * @property ?int $version_id
 * @property ?string $definition_slug
 * @property ?string $payload_json
 * @property ?int $user_id
 * @property ?string $locale
 * @property ?string $ip_hash
 * @property ?string $user_agent_hash
 * @property ?int $host_page_id
 * @property ?int $widget_connection_id
 *
 * @extends SQLEntity<ShapeEntityFormSubmission>
 */
class EntityFormSubmission extends SQLEntity
{
	public const string TABLE_NAME = 'form_submissions';

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityFormSubmission $data
	 */
	public function __construct(array $data)
	{
		parent::__construct($data);
	}

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityFormSubmission $data
	 */
	public static function saveFromArray(array $data): static
	{
		return parent::saveFromArray($data);
	}

	/**
	 * {@inheritDoc}
	 * @param ShapeEntityFormSubmission $data
	 */
	public static function createFromArray(array $data): static
	{
		return parent::createFromArray($data);
	}
}
