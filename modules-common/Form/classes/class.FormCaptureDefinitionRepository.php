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
		FormCaptureDescriptorSchemaValidator::validateForDefinition($definition_slug, $descriptor, $security);
		$normalized_security = FormCaptureDescriptorSchemaValidator::normalizeSecurity(
			$security,
			FormCaptureDescriptorSchemaValidator::validateDescriptor($descriptor),
		);
		$security_json = self::encodeJson($normalized_security);
		$descriptor_json = self::encodeJson($descriptor);
		$descriptor_hash = hash('sha256', $descriptor_json);
		$definition = EntityFormDefinition::findBySlug($definition_slug);

		if ($definition === null) {
			$definition = EntityFormDefinition::createFromArray([
				'definition_slug' => $definition_slug,
				'kind' => 'capture',
				'source' => $source,
				'status' => 'published',
				'owner_user_id' => $owner_user_id,
				'security_json' => $security_json,
				'published_version_id' => null,
			]);
		} else {
			$definition = EntityFormDefinition::updateById((int)$definition->definition_id, [
				'kind' => 'capture',
				'source' => $source,
				'status' => 'published',
				'owner_user_id' => $owner_user_id,
				'security_json' => $security_json,
			]);
		}

		$definition_id = (int)$definition->definition_id;
		$version = EntityFormDefinitionVersion::findFirst([
			'definition_id' => $definition_id,
			'descriptor_hash' => $descriptor_hash,
		]);

		if ($version === null) {
			$version = EntityFormDefinitionVersion::createFromArray([
				'definition_id' => $definition_id,
				'version_number' => $this->nextVersionNumber($definition_id),
				'status' => 'published',
				'descriptor_json' => $descriptor_json,
				'descriptor_hash' => $descriptor_hash,
				'published_at' => date('Y-m-d H:i:s'),
			]);
		} else {
			$version = EntityFormDefinitionVersion::updateById((int)$version->version_id, [
				'status' => 'published',
				'descriptor_json' => $descriptor_json,
				'published_at' => date('Y-m-d H:i:s'),
			]);
		}

		$definition = EntityFormDefinition::updateById($definition_id, [
			'status' => 'published',
			'published_version_id' => (int)$version->version_id,
		]);

		return FormDefinitionResolution::capture(
			$definition_slug,
			$definition->dto(),
			$version->dto(),
			$descriptor,
			$normalized_security,
		);
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

		$definition = EntityFormDefinition::findBySlug($definition_slug);

		if ($definition === null || $definition->kind !== 'capture' || $definition->status !== 'published') {
			return null;
		}

		$version_id = (int)($definition->published_version_id ?? 0);

		if ($version_id <= 0) {
			return null;
		}

		$version = EntityFormDefinitionVersion::findPublishedForDefinition((int)$definition->definition_id, $version_id);

		if ($version === null) {
			return null;
		}

		try {
			$descriptor = self::decodeJsonObject((string)$version->descriptor_json, 'descriptor_json');
			FormCaptureDescriptorSchemaValidator::validateDescriptor($descriptor);
			$security = FormCaptureDescriptorSchemaValidator::normalizeSecurity(
				(string)$definition->security_json,
				FormCaptureDescriptorSchemaValidator::validateDescriptor($descriptor),
			);
			FormCaptureDescriptorSchemaValidator::validateForDefinition($definition_slug, $descriptor, $security);
		} catch (Throwable $exception) {
			throw FormCaptureRuntimeException::invalidDescriptor($definition_slug, $exception);
		}

		return FormDefinitionResolution::capture(
			$definition_slug,
			$definition->dto(),
			$version->dto(),
			$descriptor,
			$security,
		);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function encodeJson(array $data): string
	{
		return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
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
