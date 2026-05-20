<?php

declare(strict_types=1);

final class FormCaptureDefinitionRepository
{
	/**
	 * @param array<string, mixed> $descriptor
	 * @param array<string, mixed>|string|null $security
	 */
	public function upsertPublishedDefinition(
		string $definition_slug,
		array $descriptor,
		array|string|null $security = null,
		string $source = 'shipped',
		?int $owner_user_id = null,
	): FormDefinitionResolution {
		return $this->publishDefinition($definition_slug, $descriptor, $security, $source, $owner_user_id);
	}

	/**
	 * @param array<string, mixed> $descriptor
	 * @param array<string, mixed>|string|null $security
	 * @return array<string, mixed>
	 */
	public function previewPublishDefinition(
		string $definition_slug,
		array $descriptor,
		array|string|null $security = null,
		string $source = 'db',
		?int $owner_user_id = null,
	): array {
		$this->assertDefinitionTablesInstalled();
		$source = $this->normalizeSource($source);
		$prepared = $this->preparePublishPayload($definition_slug, $descriptor, $security);
		$definition = EntityFormDefinition::findBySlug($definition_slug);
		$this->assertSourceMatches($definition, $source, $definition_slug);
		$version = $definition instanceof EntityFormDefinition
			? EntityFormDefinitionVersion::findFirst([
				'definition_id' => (int)$definition->definition_id,
				'descriptor_hash' => $prepared['descriptor_hash'],
			])
			: null;

		return [
			'status' => 'success',
			'dry_run' => true,
			'definition_slug' => $definition_slug,
			'source' => $source,
			'owner_user_id' => $owner_user_id,
			'definition_action' => $definition instanceof EntityFormDefinition ? 'update' : 'create',
			'version_action' => $version instanceof EntityFormDefinitionVersion ? 'reuse' : 'create',
			'version_number' => $version instanceof EntityFormDefinitionVersion
				? (int)$version->version_number
				: ($definition instanceof EntityFormDefinition ? $this->nextVersionNumber((int)$definition->definition_id) : 1),
			'descriptor_hash' => $prepared['descriptor_hash'],
			'normalized_descriptor_hash' => FormCaptureCompiledDescriptorCache::hashData($prepared['descriptor']),
			'security_hash' => FormCaptureCompiledDescriptorCache::hashData($prepared['security']),
		];
	}

	/**
	 * @param array<string, mixed> $descriptor
	 * @param array<string, mixed>|string|null $security
	 */
	public function publishDefinition(
		string $definition_slug,
		array $descriptor,
		array|string|null $security = null,
		string $source = 'db',
		?int $owner_user_id = null,
	): FormDefinitionResolution {
		$this->assertDefinitionTablesInstalled();
		$source = $this->normalizeSource($source);
		$prepared = $this->preparePublishPayload($definition_slug, $descriptor, $security);
		$pdo = Db::instance();
		$started_transaction = !$pdo->inTransaction();
		$cache = new FormCaptureCompiledDescriptorCache();

		try {
			if ($started_transaction) {
				$pdo->beginTransaction();
			}

			$definition = EntityFormDefinition::findBySlug($definition_slug);
			$this->assertSourceMatches($definition, $source, $definition_slug);

			if ($definition === null) {
				$definition = EntityFormDefinition::createFromArray([
					'definition_slug' => $definition_slug,
					'kind' => 'capture',
					'source' => $source,
					'status' => 'published',
					'owner_user_id' => $owner_user_id,
					'security_json' => $prepared['security_json'],
					'published_version_id' => null,
				]);
			} else {
				$definition = EntityFormDefinition::updateById((int)$definition->definition_id, [
					'kind' => 'capture',
					'source' => $source,
					'status' => 'published',
					'owner_user_id' => $owner_user_id,
					'security_json' => $prepared['security_json'],
				]);
			}

			$definition_id = (int)$definition->definition_id;
			$version = EntityFormDefinitionVersion::findFirst([
				'definition_id' => $definition_id,
				'descriptor_hash' => $prepared['descriptor_hash'],
			]);

			if ($version === null) {
				$version = EntityFormDefinitionVersion::createFromArray([
					'definition_id' => $definition_id,
					'version_number' => $this->nextVersionNumber($definition_id),
					'status' => 'published',
					'descriptor_json' => $prepared['descriptor_json'],
					'descriptor_hash' => $prepared['descriptor_hash'],
					'published_at' => date('Y-m-d H:i:s'),
				]);
			} else {
				$version = EntityFormDefinitionVersion::updateById((int)$version->version_id, [
					'status' => 'published',
					'descriptor_json' => $prepared['descriptor_json'],
					'published_at' => date('Y-m-d H:i:s'),
				]);
			}

			$cache->write($definition->dto(), $version->dto(), $prepared['descriptor'], $prepared['security']);

			$definition = EntityFormDefinition::updateById($definition_id, [
				'status' => 'published',
				'published_version_id' => (int)$version->version_id,
			]);

			if ($started_transaction) {
				$pdo->commit();
			}

			try {
				$cache->deleteStaleForSlug($definition_slug, (int)$version->version_number);
			} catch (Throwable $exception) {
				error_log('[form-capture-cache] failed to delete stale descriptor cache: ' . $exception->getMessage());
			}

			return FormDefinitionResolution::capture(
				$definition_slug,
				$definition->dto(),
				$version->dto(),
				$prepared['descriptor'],
				$prepared['security'],
			);
		} catch (Throwable $exception) {
			if ($started_transaction && $pdo->inTransaction()) {
				$pdo->rollBack();
			}

			throw $exception;
		}
	}

	public function findPublishedResolution(string $definition_slug): ?FormDefinitionResolution
	{
		if (!FormCaptureDescriptorSchemaValidator::isCaptureSlug($definition_slug)) {
			return null;
		}

		if (!$this->tableExists('form_definitions') || !$this->tableExists('form_definition_versions')) {
			return null;
		}

		try {
			FormCaptureDescriptorSchemaValidator::validateDefinitionSlug($definition_slug);
		} catch (InvalidArgumentException) {
			return null;
		}

		$metadata = $this->fetchPublishedMetadata($definition_slug);

		if ($metadata === null) {
			return null;
		}

		$definition = $metadata['definition'];
		$version = $metadata['version'];
		$cached = $this->resolutionFromCache($definition_slug, $definition, $version);

		if ($cached instanceof FormDefinitionResolution) {
			return $cached;
		}

		try {
			$descriptor_json = $this->fetchPublishedDescriptorJson((int)$definition['definition_id'], (int)$version['version_id']);

			if ($descriptor_json === null) {
				return null;
			}

			if (!hash_equals((string)$version['descriptor_hash'], hash('sha256', $descriptor_json))) {
				throw new InvalidArgumentException('Capture form descriptor_hash does not match descriptor_json.');
			}

			$descriptor = FormCaptureDescriptorSchemaValidator::normalizeDescriptor(
				self::decodeJsonObject($descriptor_json, 'descriptor_json'),
			);
			$field_keys = FormCaptureDescriptorSchemaValidator::validateDescriptor($descriptor);
			$security = FormCaptureDescriptorSchemaValidator::normalizeSecurity((string)$definition['security_json'], $field_keys);
			FormCaptureDescriptorSchemaValidator::validateForDefinition($definition_slug, $descriptor, $security);
		} catch (Throwable $exception) {
			throw FormCaptureRuntimeException::invalidDescriptor($definition_slug, $exception);
		}

		try {
			(new FormCaptureCompiledDescriptorCache())->write($definition, $version, $descriptor, $security);
		} catch (Throwable $exception) {
			error_log('[form-capture-cache] failed to write compiled descriptor cache: ' . $exception->getMessage());
		}

		return FormDefinitionResolution::capture(
			$definition_slug,
			$definition,
			$version,
			$descriptor,
			$security,
		);
	}

	/**
	 * @return array{definition: array<string, mixed>, version: array<string, mixed>}|null
	 */
	private function fetchPublishedMetadata(string $definition_slug): ?array
	{
		$row = DbHelper::selectOneFromQuery(
			"SELECT
				d.definition_id,
				d.definition_slug,
				d.kind,
				d.source,
				d.status,
				d.owner_user_id,
				d.security_json,
				d.published_version_id,
				v.version_id,
				v.version_number,
				v.status AS version_status,
				v.descriptor_hash,
				v.published_at
			FROM form_definitions d
			INNER JOIN form_definition_versions v
				ON v.version_id = d.published_version_id
				AND v.definition_id = d.definition_id
				AND v.status = 'published'
			WHERE d.definition_slug = ?
			  AND d.kind = 'capture'
			  AND d.status = 'published'
			LIMIT 1",
			[$definition_slug],
		);

		if (!is_array($row)) {
			return null;
		}

		return [
			'definition' => [
				'definition_id' => (int)$row['definition_id'],
				'definition_slug' => (string)$row['definition_slug'],
				'kind' => (string)$row['kind'],
				'source' => (string)$row['source'],
				'status' => (string)$row['status'],
				'owner_user_id' => $row['owner_user_id'] === null ? null : (int)$row['owner_user_id'],
				'security_json' => (string)$row['security_json'],
				'published_version_id' => (int)$row['published_version_id'],
			],
			'version' => [
				'version_id' => (int)$row['version_id'],
				'definition_id' => (int)$row['definition_id'],
				'version_number' => (int)$row['version_number'],
				'status' => (string)$row['version_status'],
				'descriptor_hash' => (string)$row['descriptor_hash'],
				'published_at' => $row['published_at'] === null ? null : (string)$row['published_at'],
			],
		];
	}

	private function fetchPublishedDescriptorJson(int $definition_id, int $version_id): ?string
	{
		$descriptor_json = DbHelper::selectOneColumnFromQuery(
			"SELECT descriptor_json
			FROM form_definition_versions
			WHERE definition_id=?
			  AND version_id=?
			  AND status='published'
			LIMIT 1",
			[$definition_id, $version_id],
		);

		return is_string($descriptor_json) ? $descriptor_json : null;
	}

	/**
	 * @param array<string, mixed> $definition
	 * @param array<string, mixed> $version
	 */
	private function resolutionFromCache(string $definition_slug, array $definition, array $version): ?FormDefinitionResolution
	{
		$entry = (new FormCaptureCompiledDescriptorCache())->read($definition, $version);

		if ($entry === null) {
			return null;
		}

		try {
			$descriptor = FormCaptureDescriptorSchemaValidator::normalizeDescriptor($entry['descriptor']);
			$field_keys = FormCaptureDescriptorSchemaValidator::validateDescriptor($descriptor);
			$security = FormCaptureDescriptorSchemaValidator::normalizeSecurity((string)$definition['security_json'], $field_keys);
			$descriptor_hash = FormCaptureCompiledDescriptorCache::hashData($descriptor);

			if (
				!hash_equals((string)($entry['normalized_descriptor_hash'] ?? ''), $descriptor_hash)
				|| !$this->descriptorHashMatchesPublishedVersion($definition, $version, $descriptor_hash)
				|| !hash_equals((string)($entry['security_hash'] ?? ''), FormCaptureCompiledDescriptorCache::hashData($security))
			) {
				return null;
			}

			FormCaptureDescriptorSchemaValidator::validateForDefinition($definition_slug, $descriptor, $security);
		} catch (Throwable) {
			return null;
		}

		return FormDefinitionResolution::capture(
			$definition_slug,
			$definition,
			$version,
			$descriptor,
			$security,
		);
	}

	/**
	 * @param array<string, mixed> $definition
	 * @param array<string, mixed> $version
	 */
	private function descriptorHashMatchesPublishedVersion(array $definition, array $version, string $normalized_descriptor_hash): bool
	{
		$version_descriptor_hash = (string)$version['descriptor_hash'];

		if (hash_equals($version_descriptor_hash, $normalized_descriptor_hash)) {
			return true;
		}

		$descriptor_json = $this->fetchPublishedDescriptorJson((int)$definition['definition_id'], (int)$version['version_id']);

		if ($descriptor_json === null || !hash_equals($version_descriptor_hash, hash('sha256', $descriptor_json))) {
			return false;
		}

		$stored_descriptor = FormCaptureDescriptorSchemaValidator::normalizeDescriptor(
			self::decodeJsonObject($descriptor_json, 'descriptor_json'),
		);

		return hash_equals($normalized_descriptor_hash, FormCaptureCompiledDescriptorCache::hashData($stored_descriptor));
	}

	/**
	 * @param array<string, mixed> $descriptor
	 * @param array<string, mixed>|string|null $security
	 * @return array{descriptor: array<string, mixed>, security: array<string, mixed>, descriptor_json: string, descriptor_hash: string, security_json: string}
	 */
	private function preparePublishPayload(string $definition_slug, array $descriptor, array|string|null $security): array
	{
		FormCaptureDescriptorSchemaValidator::validateDefinitionSlug($definition_slug);
		$normalized_descriptor = FormCaptureDescriptorSchemaValidator::normalizeDescriptor($descriptor);
		$field_keys = FormCaptureDescriptorSchemaValidator::validateDescriptor($normalized_descriptor);
		$normalized_security = FormCaptureDescriptorSchemaValidator::normalizeSecurity($security, $field_keys);
		FormCaptureDescriptorSchemaValidator::validateForDefinition($definition_slug, $normalized_descriptor, $normalized_security);
		$descriptor_json = self::encodeJson($normalized_descriptor);

		return [
			'descriptor' => $normalized_descriptor,
			'security' => $normalized_security,
			'descriptor_json' => $descriptor_json,
			'descriptor_hash' => hash('sha256', $descriptor_json),
			'security_json' => self::encodeJson($normalized_security),
		];
	}

	private function normalizeSource(string $source): string
	{
		$source = trim($source);

		if (!in_array($source, ['db', 'shipped'], true)) {
			throw new InvalidArgumentException('Capture form source must be db or shipped.');
		}

		return $source;
	}

	private function assertSourceMatches(?EntityFormDefinition $definition, string $source, string $definition_slug): void
	{
		if ($definition === null) {
			return;
		}

		$existing_source = trim((string)($definition->source ?? ''));

		if ($existing_source !== '' && $existing_source !== $source) {
			throw new InvalidArgumentException("Capture form {$definition_slug} already has source {$existing_source}; refusing {$source} publish.");
		}
	}

	private function assertDefinitionTablesInstalled(): void
	{
		if (!$this->tableExists('form_definitions') || !$this->tableExists('form_definition_versions')) {
			throw new RuntimeException('Capture form definition tables are not installed.');
		}
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function encodeJson(array $data): string
	{
		return FormCaptureCompiledDescriptorCache::encodeJson($data);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function decodeJsonObject(string $json, string $label): array
	{
		try {
			$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new InvalidArgumentException("Capture form {$label} must be valid JSON.", 0, $exception);
		}

		if (!is_array($data)) {
			throw new InvalidArgumentException("Capture form {$label} must decode to an object.");
		}

		return $data;
	}

	private function nextVersionNumber(int $definition_id): int
	{
		return (int)DbHelper::selectOneColumnFromQuery(
			'SELECT COALESCE(MAX(version_number), 0) + 1 FROM form_definition_versions WHERE definition_id=?',
			[$definition_id],
		);
	}

	private function tableExists(string $table): bool
	{
		$stmt = Db::instance()->prepare(
			"SELECT 1
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_TYPE = 'BASE TABLE'
			  AND TABLE_NAME = ?"
		);
		$stmt->execute([$table]);

		return (bool)$stmt->fetchColumn();
	}
}
