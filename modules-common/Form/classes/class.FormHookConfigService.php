<?php

declare(strict_types=1);

final class FormHookConfigService
{
	/**
	 * @return array<string, mixed>
	 */
	public function listForForm(string $definition_slug): array
	{
		$definition = $this->requireDefinition($definition_slug);
		$discovery = FormHookTargetRegistry::discoveryPayload();
		$fields = $this->fieldsForDefinition($definition);

		return [
			'definition' => [
				'definition_id' => (int)$definition->definition_id,
				'definition_slug' => (string)$definition->definition_slug,
			],
			'targets' => $discovery['targets'],
			'presets' => $discovery['presets'],
			'hooks' => array_map(
				fn (EntityFormHookTarget $hook): array => $this->hookToArray($hook),
				EntityFormHookTarget::findMany(['definition_id' => (int)$definition->definition_id], 'hook_id ASC'),
			),
			'fields' => $fields,
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function saveForForm(string $definition_slug, array $input): array
	{
		$this->assertTablesInstalled();
		$this->assertAllowedInputKeys($input);
		$definition = $this->requireDefinition($definition_slug);
		$hook_id = (int)($input['hook_id'] ?? 0);
		$existing = $this->existingHookForDefinition($definition, $hook_id);

		if ($hook_id > 0 && !($existing instanceof EntityFormHookTarget)) {
			throw new FormHookConfigValidationException('FORM_HOOK_NOT_FOUND', 'common.error_save', 404);
		}

		$prepared = $this->prepareConfig($definition, $input, $existing);
		$user_id = User::getCurrentUserId();

		if ($existing instanceof EntityFormHookTarget) {
			$hook = EntityFormHookTarget::updateById((int)$existing->hook_id, $prepared + [
				'updated_by_user_id' => $user_id > 0 ? $user_id : null,
			]);
		} else {
			$hook = EntityFormHookTarget::createFromArray($prepared + [
				'definition_id' => (int)$definition->definition_id,
				'created_by_user_id' => $user_id > 0 ? $user_id : null,
				'updated_by_user_id' => $user_id > 0 ? $user_id : null,
			]);
		}

		return [
			'hook' => $this->hookToArray($hook),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function deleteForForm(string $definition_slug, int $hook_id): array
	{
		$this->assertTablesInstalled();
		$definition = $this->requireDefinition($definition_slug);
		$hook = $this->existingHookForDefinition($definition, $hook_id);

		if (!$hook instanceof EntityFormHookTarget) {
			throw new FormHookConfigValidationException('FORM_HOOK_NOT_FOUND', 'common.error_save', 404);
		}

		return [
			'deleted' => EntityFormHookTarget::delete((int)$hook->hook_id),
			'hook_id' => (int)$hook->hook_id,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function deliveriesForForm(string $definition_slug, int $limit = 25): array
	{
		$this->assertTablesInstalled();
		$definition = $this->requireDefinition($definition_slug);
		$limit = min(100, max(1, $limit));
		$rows = DbHelper::selectManyFromQuery(
			"SELECT *
			FROM form_hook_deliveries
			WHERE definition_id = ?
			ORDER BY delivery_id DESC
			LIMIT {$limit}",
			[(int)$definition->definition_id],
		);

		return [
			'deliveries' => array_map(fn (array $row): array => $this->deliveryRowToArray($row), $rows),
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	private function prepareConfig(EntityFormDefinition $definition, array $input, ?EntityFormHookTarget $existing): array
	{
		$is_system_developer = Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER);
		$target_kind = FormHookTargetRegistry::normalizeKind((string)($input['target_kind'] ?? $existing?->target_kind ?? ''));

		if ($target_kind === '') {
			throw new FormHookConfigValidationException(
				'FORM_HOOK_TARGET_KIND_REQUIRED',
				'common.error_save',
				422,
				['target_kind' => ['required']],
			);
		}
		$target = FormHookTargetRegistry::get($target_kind);

		if (!$target instanceof iFormHookTarget) {
			throw new FormHookConfigValidationException(
				'FORM_HOOK_UNKNOWN_TARGET_KIND',
				'common.error_save',
				422,
				['target_kind' => ['unknown']],
			);
		}

		$target_definition = $target->definition();

		if ($target_definition->requiresSystemDeveloper && !$is_system_developer) {
			throw FormHookConfigValidationException::developerRoleRequired('target_kind');
		}

		$url = $this->optionalString($input['url'] ?? $existing?->url ?? null, 2048);
		$preset_key = $this->optionalIdentifier($input['preset_key'] ?? $existing?->preset_key ?? null);
		$metadata = $this->metadataFromInput($input, $existing);
		$excluded_field_keys = $this->excludedFieldKeysFromInput($input, $existing);
		$field_keys = $this->fieldKeysForDefinition($definition);
		$enable_in_non_production = $this->boolValue($input['enable_in_non_production'] ?? $existing?->enable_in_non_production ?? false);

		if (!$target_definition->supportsUrl) {
			$url = null;
		}

		if (!$target_definition->supportsPresetKey) {
			$preset_key = null;
		}

		if ($url !== null && $url !== '' && !$is_system_developer) {
			throw FormHookConfigValidationException::developerRoleRequired('url');
		}

		if ($enable_in_non_production && !$is_system_developer) {
			throw FormHookConfigValidationException::developerRoleRequired('enable_in_non_production');
		}

		foreach ($excluded_field_keys as $field_key) {
			if (!in_array($field_key, $field_keys, true)) {
				throw new FormHookConfigValidationException(
					'FORM_HOOK_UNKNOWN_EXCLUDED_FIELD_KEY',
					'common.error_save',
					422,
					['excluded_field_keys' => ['unknown_field_key']],
					['field_key' => $field_key],
				);
			}
		}

		$config = [
			'target_kind' => $target_kind,
			'url' => $url,
			'preset_key' => $preset_key,
			'metadata' => $metadata,
			'excluded_field_keys' => $excluded_field_keys,
			'enable_in_non_production' => $enable_in_non_production,
		];
		$target->validateConfig($config, $field_keys, $is_system_developer);

		$label = t($target_definition->nameKey);

		$save_data = [
			'target_kind' => $target_kind,
			'enabled' => $this->boolValue($input['enabled'] ?? $existing?->enabled ?? true) ? 1 : 0,
			'label' => mb_substr($label, 0, 190),
			'url' => $url === '' ? null : $url,
			'preset_key' => $preset_key === '' ? null : $preset_key,
			'metadata_json' => FormCaptureCompiledDescriptorCache::encodeJson($metadata),
			'excluded_field_keys_json' => FormCaptureCompiledDescriptorCache::encodeJson($excluded_field_keys),
			'enable_in_non_production' => $enable_in_non_production ? 1 : 0,
		];

		return $save_data + $this->secretSaveData($input, $existing, $is_system_developer, $target_definition->supportsSecret);
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function assertAllowedInputKeys(array $input): void
	{
		$allowed = [
			'definition_slug' => true,
			'hook_id' => true,
			'target_kind' => true,
			'url' => true,
			'preset_key' => true,
			'enabled' => true,
			'enable_in_non_production' => true,
			'metadata_json' => true,
			'excluded_field_keys_json' => true,
			'csrf_token' => true,
			'secret' => true,
		];

		foreach (array_keys($input) as $key) {
			if (!isset($allowed[(string)$key])) {
				throw new FormHookConfigValidationException(
					'FORM_HOOK_UNKNOWN_FIELD',
					'common.error_save',
					422,
					[(string)$key => ['unknown']],
				);
			}
		}
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array{secret_ciphertext: string|null, secret_nonce: string|null, secret_tag: string|null}
	 */
	private function secretSaveData(array $input, ?EntityFormHookTarget $existing, bool $is_system_developer, bool $supports_secret): array
	{
		if (!$supports_secret) {
			return [
				'secret_ciphertext' => null,
				'secret_nonce' => null,
				'secret_tag' => null,
			];
		}

		if (!array_key_exists('secret', $input)) {
			if (!($existing instanceof EntityFormHookTarget)) {
				throw new FormHookConfigValidationException('FORM_HOOK_SECRET_REQUIRED', 'common.error_save', 422, ['secret' => ['required']]);
			}

			return [
				'secret_ciphertext' => $existing?->secret_ciphertext,
				'secret_nonce' => $existing?->secret_nonce,
				'secret_tag' => $existing?->secret_tag,
			];
		}

		$secret = is_scalar($input['secret']) ? trim((string)$input['secret']) : '';

		if ($secret !== '' && !$is_system_developer) {
			throw FormHookConfigValidationException::developerRoleRequired('secret');
		}

		if ($secret === '' && !($existing instanceof EntityFormHookTarget)) {
			throw new FormHookConfigValidationException('FORM_HOOK_SECRET_REQUIRED', 'common.error_save', 422, ['secret' => ['required']]);
		}

		return FormHookSecretStore::encryptNullable($secret);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function metadataFromInput(array $input, ?EntityFormHookTarget $existing): array
	{
		if (array_key_exists('metadata_json', $input)) {
			return $this->decodeJsonObject((string)$input['metadata_json'], 'metadata_json');
		}

		return $existing instanceof EntityFormHookTarget
			? $this->decodeJsonObject((string)$existing->metadata_json, 'metadata_json')
			: [];
	}

	/**
	 * @return list<string>
	 */
	private function excludedFieldKeysFromInput(array $input, ?EntityFormHookTarget $existing): array
	{
		if (array_key_exists('excluded_field_keys_json', $input)) {
			$value = $this->decodeJsonList((string)$input['excluded_field_keys_json'], 'excluded_field_keys_json');
		} elseif ($existing instanceof EntityFormHookTarget) {
			$value = $this->decodeJsonList((string)$existing->excluded_field_keys_json, 'excluded_field_keys_json');
		} else {
			$value = [];
		}

		if (!is_array($value) || !array_is_list($value)) {
			throw new FormHookConfigValidationException('FORM_HOOK_INVALID_EXCLUDED_FIELDS', 'common.error_save', 422, ['excluded_field_keys' => ['list_required']]);
		}

		$keys = [];

		foreach ($value as $field_key) {
			if (!is_scalar($field_key)) {
				throw new FormHookConfigValidationException('FORM_HOOK_INVALID_EXCLUDED_FIELDS', 'common.error_save', 422, ['excluded_field_keys' => ['strings_required']]);
			}

			$field_key = trim((string)$field_key);

			if ($field_key !== '' && !in_array($field_key, $keys, true)) {
				$keys[] = $field_key;
			}
		}

		return $keys;
	}

	private function existingHookForDefinition(EntityFormDefinition $definition, int $hook_id): ?EntityFormHookTarget
	{
		if ($hook_id <= 0) {
			return null;
		}

		$hook = EntityFormHookTarget::findFirst([
			'hook_id' => $hook_id,
			'definition_id' => (int)$definition->definition_id,
		]);

		return $hook instanceof EntityFormHookTarget ? $hook : null;
	}

	private function requireDefinition(string $definition_slug): EntityFormDefinition
	{
		$this->assertTablesInstalled();
		$definition_slug = trim($definition_slug);
		$definition = EntityFormDefinition::findBySlug($definition_slug);

		if (!$definition instanceof EntityFormDefinition || (string)$definition->kind !== 'capture') {
			throw new FormHookConfigValidationException('FORM_HOOK_FORM_NOT_FOUND', 'common.error_save', 404);
		}

		return $definition;
	}

	/**
	 * @return list<string>
	 */
	private function fieldKeysForDefinition(EntityFormDefinition $definition): array
	{
		$keys = [];

		foreach ($this->fieldsForDefinition($definition) as $field) {
			$keys[] = $field['key'];
		}

		return $keys;
	}

	/**
	 * @return list<array{key: string, label: string, name: string, type: string}>
	 */
	private function fieldsForDefinition(EntityFormDefinition $definition): array
	{
		$version = EntityFormDefinitionVersion::findFirst(
			['definition_id' => (int)$definition->definition_id, 'status' => 'draft'],
			'version_number DESC, version_id DESC',
		);

		if (!$version instanceof EntityFormDefinitionVersion && (int)($definition->published_version_id ?? 0) > 0) {
			$version = EntityFormDefinitionVersion::findById((int)$definition->published_version_id);
		}

		if (!$version instanceof EntityFormDefinitionVersion) {
			return [];
		}

		try {
			$descriptor = $this->decodeJsonObject((string)$version->descriptor_json, 'descriptor_json');
			$descriptor = FormCaptureDescriptorSchemaValidator::normalizeDescriptor($descriptor);
			FormCaptureDescriptorSchemaValidator::validateDescriptor($descriptor);
			$fields = [];
			$seen = [];

			foreach (($descriptor['fields'] ?? []) as $field) {
				if (!is_array($field)) {
					continue;
				}

				$name = trim((string)($field['name'] ?? ''));
				$key = trim((string)($field['key'] ?? $name));

				if ($key === '' || isset($seen[$key])) {
					continue;
				}

				$seen[$key] = true;
				$fields[] = [
					'key' => $key,
					'label' => $this->fieldLabel($field, $key),
					'name' => $name,
					'type' => trim((string)($field['type'] ?? 'text')),
				];
			}

			return $fields;
		} catch (Throwable) {
			return [];
		}
	}

	/**
	 * @param array<string, mixed> $field
	 */
	private function fieldLabel(array $field, string $fallback): string
	{
		if (array_key_exists('label', $field)) {
			try {
				$label = trim(FormDescriptorAdapter::resolveDescriptorText($field['label']));

				if ($label !== '') {
					return $label;
				}
			} catch (Throwable) {
				// Invalid descriptor labels fall back to stable field keys.
			}
		}

		return $fallback;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function deliveryRowToArray(array $row): array
	{
		$result = $this->decodeJsonObjectOrNull($row['result_json'] ?? null);
		$error_message = $row['error_message'] === null ? null : (string)$row['error_message'];
		$queued_reference = $this->queuedReference($result);

		return [
			'delivery_id' => (int)($row['delivery_id'] ?? 0),
			'hook_id' => $row['hook_id'] === null ? null : (int)$row['hook_id'],
			'definition_id' => (int)($row['definition_id'] ?? 0),
			'version_id' => (int)($row['version_id'] ?? 0),
			'submission_id' => (int)($row['submission_id'] ?? 0),
			'target_kind' => (string)($row['target_kind'] ?? ''),
			'target_label' => (string)($row['target_label'] ?? ''),
			'status' => (string)($row['status'] ?? ''),
			'environment' => (string)($row['environment'] ?? ''),
			'payload' => $this->decodeJsonObjectOrNull($row['payload_json'] ?? null),
			'result' => $result,
			'queued_reference' => $queued_reference,
			'http_status' => is_array($result) && isset($result['http_status']) ? (int)$result['http_status'] : null,
			'error_code' => $row['error_code'] === null ? null : (string)$row['error_code'],
			'error_message' => $error_message,
			'message' => $error_message ?? (is_array($result) ? (string)($result['message'] ?? $queued_reference ?? '') : ''),
			'error' => $error_message,
			'queued_at' => $row['queued_at'] === null ? null : (string)$row['queued_at'],
			'completed_at' => $row['completed_at'] === null ? null : (string)$row['completed_at'],
			'created_at' => $row['created_at'] === null ? null : (string)$row['created_at'],
		];
	}

	/**
	 * @param array<string, mixed>|null $result
	 */
	private function queuedReference(?array $result): ?string
	{
		if (!is_array($result)) {
			return null;
		}

		if (isset($result['job_id']) && is_scalar($result['job_id'])) {
			$job_id = trim((string)$result['job_id']);

			return $job_id === '' ? null : $job_id;
		}

		if (isset($result['outbox_id']) && is_scalar($result['outbox_id'])) {
			$outbox_id = (int)$result['outbox_id'];
			$queued_jobs = isset($result['queued_jobs']) && is_scalar($result['queued_jobs']) ? (int)$result['queued_jobs'] : 0;

			return $outbox_id > 0 ? "email_outbox:{$outbox_id}/jobs:{$queued_jobs}" : null;
		}

		return null;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function hookToArray(EntityFormHookTarget $hook): array
	{
		$has_secret = trim((string)($hook->secret_ciphertext ?? '')) !== ''
			&& trim((string)($hook->secret_nonce ?? '')) !== ''
			&& trim((string)($hook->secret_tag ?? '')) !== '';

		return [
			'hook_id' => (int)$hook->hook_id,
			'definition_id' => (int)$hook->definition_id,
			'target_kind' => (string)$hook->target_kind,
			'enabled' => (bool)(int)$hook->enabled,
			'label' => (string)$hook->label,
			'url' => $hook->url === null ? '' : (string)$hook->url,
			'preset_key' => $hook->preset_key === null ? '' : (string)$hook->preset_key,
			'metadata' => $this->decodeJsonObjectOrNull($hook->metadata_json) ?? [],
			'excluded_field_keys' => $this->decodeStringList((string)$hook->excluded_field_keys_json),
			'enable_in_non_production' => (bool)(int)$hook->enable_in_non_production,
			'has_secret' => $has_secret,
			'recent_logs' => $this->recentLogsForHook((int)$hook->hook_id),
		];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function recentLogsForHook(int $hook_id): array
	{
		if ($hook_id <= 0) {
			return [];
		}

		$rows = DbHelper::selectManyFromQuery(
			"SELECT *
			FROM form_hook_deliveries
			WHERE hook_id = ?
			ORDER BY delivery_id DESC
			LIMIT 10",
			[$hook_id],
		);

		return array_map(fn (array $row): array => $this->deliveryRowToArray($row), $rows);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function decodeJsonObject(string $json, string $label): array
	{
		try {
			$root = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new FormHookConfigValidationException('FORM_HOOK_INVALID_JSON', 'common.error_save', 422, [$label => ['invalid_json']], ['message' => $exception->getMessage()]);
		}

		if (!$root instanceof stdClass) {
			throw new FormHookConfigValidationException('FORM_HOOK_INVALID_JSON', 'common.error_save', 422, [$label => ['object_required']]);
		}

		$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

		if (!is_array($data)) {
			throw new FormHookConfigValidationException('FORM_HOOK_INVALID_JSON', 'common.error_save', 422, [$label => ['object_required']]);
		}

		return $data;
	}

	/**
	 * @return list<mixed>
	 */
	private function decodeJsonList(string $json, string $label): array
	{
		try {
			$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new FormHookConfigValidationException('FORM_HOOK_INVALID_JSON', 'common.error_save', 422, [$label => ['invalid_json']], ['message' => $exception->getMessage()]);
		}

		if (!is_array($data) || !array_is_list($data)) {
			throw new FormHookConfigValidationException('FORM_HOOK_INVALID_EXCLUDED_FIELDS', 'common.error_save', 422, ['excluded_field_keys' => ['list_required']]);
		}

		return $data;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function decodeJsonObjectOrNull(mixed $json): ?array
	{
		if (!is_string($json) || trim($json) === '') {
			return null;
		}

		$data = json_decode($json, true);

		return is_array($data) && !array_is_list($data) ? $data : null;
	}

	/**
	 * @return list<string>
	 */
	private function decodeStringList(string $json): array
	{
		$data = json_decode($json, true);

		if (!is_array($data) || !array_is_list($data)) {
			return [];
		}

		return array_values(array_filter(array_map(
			static fn (mixed $value): string => is_scalar($value) ? trim((string)$value) : '',
			$data,
		), static fn (string $value): bool => $value !== ''));
	}

	private function optionalString(mixed $value, int $max_length): ?string
	{
		if ($value === null) {
			return null;
		}

		if (!is_scalar($value)) {
			return null;
		}

		return mb_substr(trim((string)$value), 0, $max_length);
	}

	private function optionalIdentifier(mixed $value): ?string
	{
		$value = $this->optionalString($value, 128);

		return $value === null ? null : strtolower(preg_replace('/[^a-zA-Z0-9_.:-]+/', '-', $value) ?? '');
	}

	private function boolValue(mixed $value): bool
	{
		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value)) {
			return $value === 1;
		}

		$value = strtolower(trim((string)$value));

		return in_array($value, ['1', 'true', 'yes', 'on'], true);
	}

	private function assertTablesInstalled(): void
	{
		if (!$this->tableExists('form_hook_targets') || !$this->tableExists('form_hook_deliveries')) {
			throw new RuntimeException('Capture form hook tables are not installed.');
		}
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
