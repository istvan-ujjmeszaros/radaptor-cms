<?php

declare(strict_types=1);

final class FormCaptureAuthoringService
{
	private const string SOURCE_DB = 'db';
	private const string SOURCE_SHIPPED = 'shipped';
	private const string STATUS_DRAFT = 'draft';
	private const string STATUS_ABANDONED = 'abandoned';
	private const string STATUS_PUBLISHED = 'published';
	private const int EDIT_HISTORY_LIMIT = 75;
	private const int EDITOR_STATE_VERSION_LIMIT = 20;

	/**
	 * @return array<string, mixed>
	 */
	public function buildListState(string $source_filter = 'custom'): array
	{
		$source_filter = in_array($source_filter, ['custom', 'system'], true) ? $source_filter : 'custom';
		$definitions = $this->listDefinitions();
		$filtered_source = $source_filter === 'system' ? self::SOURCE_SHIPPED : self::SOURCE_DB;
		$filtered = array_values(array_filter(
			$definitions,
			static fn (array $definition): bool => (string)($definition['source'] ?? '') === $filtered_source,
		));

		return [
			'definitions' => $filtered,
			'all_count' => count($definitions),
			'custom_count' => count(array_filter($definitions, static fn (array $definition): bool => (string)($definition['source'] ?? '') === self::SOURCE_DB)),
			'system_count' => count(array_filter($definitions, static fn (array $definition): bool => (string)($definition['source'] ?? '') === self::SOURCE_SHIPPED)),
			'source_filter' => $source_filter,
		];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function listDefinitions(): array
	{
		if (!$this->tablesInstalled()) {
			return [];
		}

		$usage_counts = $this->usageCountsByDefinition();

		$rows = DbHelper::selectManyFromQuery(
			"SELECT
				d.definition_id,
				d.definition_slug,
				d.source,
				d.status,
				d.published_version_id,
				pv.version_number AS published_version_number,
				(
					SELECT MAX(version_number)
					FROM form_definition_versions dv
					WHERE dv.definition_id = d.definition_id
					  AND dv.status = 'draft'
				) AS draft_version_number
			FROM form_definitions d
			LEFT JOIN form_definition_versions pv
				ON pv.version_id = d.published_version_id
			WHERE d.kind = 'capture'
			ORDER BY d.definition_slug"
		);

		return array_map(static fn (array $row): array => [
			'definition_id' => (int)$row['definition_id'],
			'definition_slug' => (string)$row['definition_slug'],
			'source' => (string)$row['source'],
			'status' => (string)$row['status'],
			'published_version_id' => $row['published_version_id'] === null ? null : (int)$row['published_version_id'],
			'published_version_number' => $row['published_version_number'] === null ? null : (int)$row['published_version_number'],
			'draft_version_number' => $row['draft_version_number'] === null ? null : (int)$row['draft_version_number'],
			'read_only' => (string)$row['source'] !== self::SOURCE_DB,
			'usage_count' => $usage_counts[(string)$row['definition_slug']] ?? 0,
		], $rows);
	}

	/**
	 * @return list<array{page_id: int, path: string, slot: string, seq: int, connection_id: int}>
	 */
	public function usageForDefinition(string $definition_slug): array
	{
		$definition_slug = trim($definition_slug);

		if ($definition_slug === '' || !$this->tableExists('widget_connections') || !$this->tableExists('attributes')) {
			return [];
		}

		$rows = DbHelper::selectManyFromQuery(
			"SELECT
				wc.page_id,
				wc.slot_name,
				wc.seq,
				wc.connection_id
			FROM widget_connections wc
			INNER JOIN attributes a
				ON a.resource_name = ?
			   AND a.resource_id = wc.connection_id
			   AND a.param_name = 'definition_slug'
			   AND a.param_value = ?
			WHERE wc.widget_name = ?
			ORDER BY wc.page_id, wc.slot_name, wc.seq",
			[
				ResourceNames::WIDGET_CONNECTION,
				$definition_slug,
				$this->captureFormWidgetName(),
			],
		);

		$usage = [];

		foreach ($rows as $row) {
			$page_id = (int)($row['page_id'] ?? 0);

			if ($page_id <= 0 || !ResourceAcl::canAccessResource($page_id, ResourceAcl::_ACL_VIEW)) {
				continue;
			}

			$path = Url::getSeoUrl($page_id, false) ?? ResourceTreeHandler::getPathFromId($page_id);

			if ($path === '') {
				continue;
			}

			$usage[] = [
				'page_id' => $page_id,
				'path' => $path,
				'slot' => (string)($row['slot_name'] ?? ''),
				'seq' => (int)($row['seq'] ?? 0),
				'connection_id' => (int)($row['connection_id'] ?? 0),
			];
		}

		return $usage;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function loadDefinition(string $definition_slug): array
	{
		$this->assertTablesInstalled();
		$definition = EntityFormDefinition::findBySlug($definition_slug);

		if (!$definition instanceof EntityFormDefinition || (string)$definition->kind !== 'capture') {
			throw new InvalidArgumentException('Capture form definition does not exist.');
		}

		$active_draft = $this->findActiveDraft((int)$definition->definition_id);
		$published = $this->findPublishedVersion($definition);
		$selected_version = $active_draft ?? $published;
		$descriptor = $selected_version instanceof EntityFormDefinitionVersion
			? $this->descriptorFromVersion($definition, $selected_version)
			: $this->defaultDescriptor($definition_slug);
		$resolve_text_fallbacks = (string)$definition->source !== self::SOURCE_DB;
		$builder_descriptor = $this->descriptorForBuilder($descriptor, $resolve_text_fallbacks);
		$security = $this->securityForDefinition($definition, $descriptor);
		$translation_descriptor = (string)$definition->source !== self::SOURCE_DB
			|| ($descriptor['i18n_mode'] ?? FormCaptureDescriptorSchemaValidator::I18N_MODE_LITERAL) === FormCaptureDescriptorSchemaValidator::I18N_MODE_KEYED
			? $descriptor
			: null;

		return [
			'definition' => $definition->dto(),
			'descriptor' => $builder_descriptor,
			'server_descriptor' => $builder_descriptor,
			'security' => $security,
			'active_draft' => $active_draft instanceof EntityFormDefinitionVersion ? $active_draft->dto() : null,
			'published_version' => $published instanceof EntityFormDefinitionVersion ? $published->dto() : null,
			'versions' => $this->versionsForDefinition((int)$definition->definition_id),
			'usage' => $this->usageForDefinition($definition_slug),
			'base_server_hash' => $selected_version instanceof EntityFormDefinitionVersion ? (string)$selected_version->descriptor_hash : '',
			'loaded_version' => null,
			'read_only' => (string)$definition->source !== self::SOURCE_DB,
			'status' => $active_draft instanceof EntityFormDefinitionVersion ? self::STATUS_DRAFT : (string)$definition->status,
			'i18n_workbench_url' => $this->i18nWorkbenchUrl(),
			'translation_url' => $this->translationUrlForDefinition($definition_slug, $translation_descriptor),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function updateDraftNote(string $definition_slug, int $version_id, string $author_note): array
	{
		$definition = $this->requireDbDefinition($definition_slug);

		if ($version_id <= 0) {
			throw new InvalidArgumentException('Draft version id is required.');
		}

		$version = EntityFormDefinitionVersion::findFirst([
			'definition_id' => (int)$definition->definition_id,
			'version_id' => $version_id,
		]);

		if (!$version instanceof EntityFormDefinitionVersion) {
			throw new InvalidArgumentException('Draft version does not exist.');
		}

		$author_note = trim($author_note);

		if (function_exists('mb_substr')) {
			$author_note = mb_substr($author_note, 0, 1000);
		} else {
			$author_note = substr($author_note, 0, 1000);
		}

		$updated = EntityFormDefinitionVersion::updateById((int)$version->version_id, [
			'author_note' => $author_note === '' ? null : $author_note,
		]);

		if (!$updated instanceof EntityFormDefinitionVersion) {
			throw new RuntimeException('Draft note update did not return a version row.');
		}

		return array_replace($this->loadDefinition($definition_slug), [
			'action' => 'updated_draft_note',
			'updated_version' => $updated->dto(),
		]);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function createDefinition(string $definition_slug, string $title = ''): array
	{
		$this->assertTablesInstalled();
		$definition_slug = FormCaptureDescriptorSchemaValidator::normalizeDefinitionSlugInput($definition_slug);
		FormCaptureDescriptorSchemaValidator::validateDefinitionSlug($definition_slug);

		if (EntityFormDefinition::findBySlug($definition_slug) instanceof EntityFormDefinition) {
			throw new InvalidArgumentException('Capture form definition already exists.');
		}

		$title = trim($title) !== '' ? trim($title) : $definition_slug;
		$descriptor = $this->defaultDescriptor($definition_slug, $title);
		$prepared = $this->prepareDescriptor($definition_slug, $descriptor, null);
		$pdo = Db::instance();
		$started_transaction = !$pdo->inTransaction();

		try {
			if ($started_transaction) {
				$pdo->beginTransaction();
			}

			$user_id = User::getCurrentUserId();
			$definition = EntityFormDefinition::createFromArray([
				'definition_slug' => $definition_slug,
				'kind' => 'capture',
				'source' => self::SOURCE_DB,
				'status' => self::STATUS_DRAFT,
				'owner_user_id' => $user_id > 0 ? $user_id : null,
				'security_json' => $prepared['security_json'],
				'published_version_id' => null,
			]);
			EntityFormDefinitionVersion::createFromArray([
				'definition_id' => (int)$definition->definition_id,
				'version_number' => 1,
				'status' => self::STATUS_DRAFT,
				'descriptor_json' => $prepared['descriptor_json'],
				'descriptor_hash' => $prepared['descriptor_hash'],
				'published_at' => null,
			]);
			$this->syncDescriptorI18nRows($definition_slug, $prepared['descriptor']);

			if ($started_transaction) {
				$pdo->commit();
			}
		} catch (Throwable $exception) {
			if ($started_transaction && $pdo->inTransaction()) {
				$pdo->rollBack();
			}

			throw $exception;
		}

		return $this->loadDefinition($definition_slug) + [
			'action' => 'created',
		];
	}

	/**
	 * @param array<string, mixed> $descriptor
	 * @return array<string, mixed>
	 */
	public function saveDraft(string $definition_slug, array $descriptor, string $base_server_hash, bool $overwrite = false): array
	{
		$definition = $this->requireDbDefinition($definition_slug);
		$server_hash = $this->currentServerHash($definition);

		if (!$overwrite && $base_server_hash !== '' && !hash_equals($server_hash, $base_server_hash)) {
			return [
				'status' => 'conflict',
				'server' => $this->loadDefinition($definition_slug),
			];
		}

		$prepared = $this->prepareDescriptor($definition_slug, $descriptor, (string)$definition->security_json);
		$pdo = Db::instance();
		$started_transaction = !$pdo->inTransaction();

		try {
			if ($started_transaction) {
				$pdo->beginTransaction();
			}

			$published_match = $this->findPublishedVersionByHash((int)$definition->definition_id, $prepared['descriptor_hash']);
			$this->abandonDrafts((int)$definition->definition_id);
			$this->syncDescriptorI18nRows($definition_slug, $prepared['descriptor']);

			if ($published_match instanceof EntityFormDefinitionVersion) {
				if ($started_transaction) {
					$pdo->commit();
				}

				return $this->loadDefinition($definition_slug) + [
					'action' => 'matches_published',
					'matched_version_id' => (int)$published_match->version_id,
				];
			}

			$version = EntityFormDefinitionVersion::findFirst([
				'definition_id' => (int)$definition->definition_id,
				'descriptor_hash' => $prepared['descriptor_hash'],
			]);

			if ($version instanceof EntityFormDefinitionVersion) {
				$version = EntityFormDefinitionVersion::updateById((int)$version->version_id, [
					'status' => self::STATUS_DRAFT,
					'descriptor_json' => $prepared['descriptor_json'],
					'published_at' => null,
				]);
			} else {
				$version = EntityFormDefinitionVersion::createFromArray([
					'definition_id' => (int)$definition->definition_id,
					'version_number' => $this->nextVersionNumber((int)$definition->definition_id),
					'status' => self::STATUS_DRAFT,
					'descriptor_json' => $prepared['descriptor_json'],
					'descriptor_hash' => $prepared['descriptor_hash'],
					'published_at' => null,
				]);
			}

			EntityFormDefinition::updateById((int)$definition->definition_id, [
				'status' => $definition->published_version_id === null ? self::STATUS_DRAFT : self::STATUS_PUBLISHED,
				'security_json' => $prepared['security_json'],
			]);

			if ($started_transaction) {
				$pdo->commit();
			}
		} catch (Throwable $exception) {
			if ($started_transaction && $pdo->inTransaction()) {
				$pdo->rollBack();
			}

			throw $exception;
		}

		return $this->loadDefinition($definition_slug) + [
			'action' => 'saved_draft',
			'draft_version_id' => (int)$version->version_id,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function insertFieldIntoDraft(string $definition_slug, string $field_type, int $insert_index): array
	{
		$definition = $this->requireDbDefinition($definition_slug);
		$version = $this->findActiveDraft((int)$definition->definition_id) ?? $this->findPublishedVersion($definition);

		if (!$version instanceof EntityFormDefinitionVersion) {
			throw new InvalidArgumentException('No editable capture form version is available.');
		}

		$descriptor = $this->descriptorFromVersion($definition, $version);
		$baseline = $descriptor;
		$field = $this->newFieldDescriptorFromPalette($definition_slug, $descriptor, $field_type);
		$descriptor['fields'] = $this->insertFieldAtVisibleIndex(
			is_array($descriptor['fields'] ?? null) ? $descriptor['fields'] : [],
			$field,
			$insert_index,
		);

		$result = $this->saveDraft($definition_slug, $descriptor, (string)$version->descriptor_hash);
		$history = $this->recordEditHistory($definition, $baseline, $result, null, (int)$version->version_id);

		return $result + $history + [
			'inserted_field' => $field,
			'field_uid' => (string)($field[FormCaptureFieldIdentity::DESCRIPTOR_KEY] ?? ''),
			'insert_index' => $insert_index,
		];
	}

	/**
	 * @param array<string, mixed> $submitted
	 * @return array<string, mixed>
	 */
	public function updateFieldPropertiesInDraft(string $definition_slug, string $field_key, int $field_index, array $submitted, string $field_uid = ''): array
	{
		$definition = $this->requireDbDefinition($definition_slug);
		$version = $this->findActiveDraft((int)$definition->definition_id) ?? $this->findPublishedVersion($definition);

		if (!$version instanceof EntityFormDefinitionVersion) {
			throw new InvalidArgumentException('No editable capture form version is available.');
		}

		$descriptor = $this->descriptorFromVersion($definition, $version);
		$baseline = $descriptor;
		$fields = [];

		foreach (($descriptor['fields'] ?? []) as $field) {
			if (is_array($field)) {
				$fields[] = $field;
			}
		}
		$field_offset = $this->findFieldOffset($fields, $field_uid, $field_key, $field_index);
		$current_field = is_array($fields[$field_offset] ?? null) ? $fields[$field_offset] : [];
		$fields[$field_offset] = (new FormCaptureFieldPropertyProvider())->applySubmittedValues(
			$definition_slug,
			$descriptor,
			$current_field,
			$submitted,
			$fields,
			$field_offset,
		);
		$descriptor['fields'] = array_values($fields);
		$result = $this->saveDraft($definition_slug, $descriptor, (string)$version->descriptor_hash);
		$history = $this->recordEditHistory($definition, $baseline, $result, null, (int)$version->version_id);

		return $result + $history + [
			'updated_field' => $fields[$field_offset],
			'field_uid' => (string)($fields[$field_offset][FormCaptureFieldIdentity::DESCRIPTOR_KEY] ?? ''),
			'field_index' => $field_offset,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function removeFieldFromDraft(string $definition_slug, string $field_key, int $field_index, string $field_uid = ''): array
	{
		$definition = $this->requireDbDefinition($definition_slug);
		$version = $this->findActiveDraft((int)$definition->definition_id) ?? $this->findPublishedVersion($definition);

		if (!$version instanceof EntityFormDefinitionVersion) {
			throw new InvalidArgumentException('No editable capture form version is available.');
		}

		$descriptor = $this->descriptorFromVersion($definition, $version);
		$baseline = $descriptor;
		$fields = $this->normalizedDescriptorFields($descriptor);
		$field_offset = $this->findFieldOffset($fields, $field_uid, $field_key, $field_index);
		$this->assertVisibleFieldOffset($fields, $field_offset);

		if (count($this->visibleFieldOffsets($fields)) <= 1) {
			throw new InvalidArgumentException('Capture forms must keep at least one editable field.');
		}

		$removed_field = $fields[$field_offset];
		array_splice($fields, $field_offset, 1);
		$descriptor['fields'] = array_values($fields);
		$result = $this->saveDraft($definition_slug, $descriptor, (string)$version->descriptor_hash);
		$history = $this->recordEditHistory($definition, $baseline, $result, null, (int)$version->version_id);

		return $result + $history + [
			'removed_field' => $removed_field,
			'field_uid' => (string)($removed_field[FormCaptureFieldIdentity::DESCRIPTOR_KEY] ?? ''),
			'field_index' => $field_offset,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function moveFieldInDraft(string $definition_slug, string $field_key, int $field_index, string $direction, string $field_uid = ''): array
	{
		$definition = $this->requireDbDefinition($definition_slug);
		$version = $this->findActiveDraft((int)$definition->definition_id) ?? $this->findPublishedVersion($definition);

		if (!$version instanceof EntityFormDefinitionVersion) {
			throw new InvalidArgumentException('No editable capture form version is available.');
		}

		$direction = trim($direction);

		if (!in_array($direction, ['up', 'down'], true)) {
			throw new InvalidArgumentException('Unsupported capture form field move direction.');
		}

		$descriptor = $this->descriptorFromVersion($definition, $version);
		$baseline = $descriptor;
		$fields = $this->normalizedDescriptorFields($descriptor);
		$field_offset = $this->findFieldOffset($fields, $field_uid, $field_key, $field_index);
		$visible_offsets = $this->visibleFieldOffsets($fields);
		$visible_position = array_search($field_offset, $visible_offsets, true);

		if ($visible_position === false) {
			throw new InvalidArgumentException('Only visible capture form fields can be moved from edit mode.');
		}

		$target_position = $direction === 'up' ? $visible_position - 1 : $visible_position + 1;

		if (!isset($visible_offsets[$target_position])) {
			throw new InvalidArgumentException('Capture form field cannot be moved in that direction.');
		}

		$target_offset = $visible_offsets[$target_position];
		$moved_field = $fields[$field_offset];
		$fields[$field_offset] = $fields[$target_offset];
		$fields[$target_offset] = $moved_field;
		$descriptor['fields'] = array_values($fields);
		$result = $this->saveDraft($definition_slug, $descriptor, (string)$version->descriptor_hash);
		$history = $this->recordEditHistory($definition, $baseline, $result, null, (int)$version->version_id);

		return $result + $history + [
			'moved_field' => $moved_field,
			'field_uid' => (string)($moved_field[FormCaptureFieldIdentity::DESCRIPTOR_KEY] ?? ''),
			'field_index' => $target_offset,
			'direction' => $direction,
		];
	}

	/**
	 * @param array<string, mixed> $submitted title / description / submit_label texts
	 * @return array<string, mixed>
	 */
	public function updateFormPropertiesInDraft(string $definition_slug, array $submitted): array
	{
		$definition = $this->requireDbDefinition($definition_slug);
		$version = $this->findActiveDraft((int)$definition->definition_id) ?? $this->findPublishedVersion($definition);

		if (!$version instanceof EntityFormDefinitionVersion) {
			throw new InvalidArgumentException('No editable capture form version is available.');
		}

		$descriptor = $this->descriptorFromVersion($definition, $version);
		$baseline = $descriptor;

		foreach (['title', 'description', 'submit_label'] as $key) {
			if (array_key_exists($key, $submitted)) {
				$descriptor[$key] = $this->textDefinitionForDescriptor($descriptor, $key, (string)$submitted[$key], $definition_slug);
			}
		}

		$result = $this->saveDraft($definition_slug, $descriptor, (string)$version->descriptor_hash);
		$history = $this->recordEditHistory($definition, $baseline, $result, null, (int)$version->version_id);

		return $result + $history + [
			'updated_properties' => array_values(array_intersect(['title', 'description', 'submit_label'], array_keys($submitted))),
		];
	}

	/**
	 * Revert the working draft to the previous edit-history state of the session.
	 *
	 * @return array<string, mixed>
	 */
	public function undoEdit(string $definition_slug, string $session_token): array
	{
		return $this->applyEditHistoryStep($definition_slug, -1, $session_token);
	}

	/**
	 * Advance the working draft to the next edit-history state of the session.
	 *
	 * @return array<string, mixed>
	 */
	public function redoEdit(string $definition_slug, string $session_token): array
	{
		return $this->applyEditHistoryStep($definition_slug, 1, $session_token);
	}

	/**
	 * Make a stored version the working state again by re-activating its row — no new
	 * version row is created. The next modification branches a new version off it as
	 * usual. Recorded in the session's edit history, so a restore is itself undoable.
	 *
	 * @return array<string, mixed>
	 */
	public function restoreVersionToDraft(string $definition_slug, int $version_id, string $session_token): array
	{
		$definition = $this->requireDbDefinition($definition_slug);
		$definition_id = (int)$definition->definition_id;

		if ($version_id <= 0) {
			throw new InvalidArgumentException('Version id is required.');
		}

		$version = EntityFormDefinitionVersion::findFirst([
			'definition_id' => $definition_id,
			'version_id' => $version_id,
		]);

		if (!$version instanceof EntityFormDefinitionVersion) {
			throw new InvalidArgumentException('Version does not exist.');
		}

		$current = $this->findActiveDraft($definition_id) ?? $this->findPublishedVersion($definition);

		if ($current instanceof EntityFormDefinitionVersion && (int)$current->version_id === $version_id) {
			return $this->loadDefinition($definition_slug) + [
				'action' => 'restored_version',
				'restored_version_id' => $version_id,
			];
		}

		$baseline = $current instanceof EntityFormDefinitionVersion
			? $this->descriptorFromVersion($definition, $current)
			: $this->defaultDescriptor($definition_slug);
		$this->activateVersionRow($definition, $version, $this->descriptorFromVersion($definition, $version));

		$result = $this->loadDefinition($definition_slug);
		$history = $this->recordEditHistory(
			$definition,
			$baseline,
			$result,
			$session_token,
			$current instanceof EntityFormDefinitionVersion ? (int)$current->version_id : 0,
		);

		return $result + $history + [
			'action' => 'restored_version',
			'restored_version_id' => $version_id,
		];
	}

	/**
	 * Make a stored version row the working state: the current draft is abandoned and
	 * the row becomes the active draft (the published row simply stays the working
	 * state). Used by restore and by undo/redo steps linked to a version row.
	 *
	 * @param array<string, mixed> $descriptor the row's validated descriptor
	 */
	private function activateVersionRow(EntityFormDefinition $definition, EntityFormDefinitionVersion $version, array $descriptor): void
	{
		$definition_id = (int)$definition->definition_id;
		$pdo = Db::instance();
		$started_transaction = !$pdo->inTransaction();

		try {
			if ($started_transaction) {
				$pdo->beginTransaction();
			}

			$this->abandonDrafts($definition_id);

			// Only the definition's CURRENT published version is the working state
			// without a draft row; any other version (including stale rows still
			// labelled 'published' from earlier publishes) must become the active
			// draft, or loadDefinition() would keep resolving the newer published one.
			if ((int)$version->version_id !== (int)($definition->published_version_id ?? 0)) {
				EntityFormDefinitionVersion::updateById((int)$version->version_id, [
					'status' => self::STATUS_DRAFT,
					'published_at' => null,
				]);
			}

			$this->syncDescriptorI18nRows((string)$definition->definition_slug, $descriptor);
			EntityFormDefinition::updateById($definition_id, [
				'status' => $definition->published_version_id === null ? self::STATUS_DRAFT : self::STATUS_PUBLISHED,
			]);

			if ($started_transaction) {
				$pdo->commit();
			}
		} catch (Throwable $exception) {
			if ($started_transaction && $pdo->inTransaction()) {
				$pdo->rollBack();
			}

			throw $exception;
		}
	}

	/**
	 * Compact editor-panel state: recent versions, the active (working-state) version,
	 * usage, and the session's undo reach.
	 *
	 * @return array{versions: list<array<string, mixed>>, active_version_id: int, usage: list<array<string, mixed>>, can_undo: bool, can_redo: bool}
	 */
	public function editorStateForDefinition(string $definition_slug, string $session_token): array
	{
		$definition = $this->requireDbDefinition($definition_slug);
		$definition_id = (int)$definition->definition_id;
		$cursor = $session_token === '' ? 0 : $this->editHistoryCursor($definition_id, $session_token);
		$active = $this->findActiveDraft($definition_id) ?? $this->findPublishedVersion($definition);
		$descriptor = $active instanceof EntityFormDefinitionVersion
			? $this->descriptorFromVersion($definition, $active)
			: $this->defaultDescriptor($definition_slug);

		return [
			'versions' => array_slice($this->versionsForDefinition($definition_id), 0, self::EDITOR_STATE_VERSION_LIMIT),
			'active_version_id' => $active instanceof EntityFormDefinitionVersion ? (int)$active->version_id : 0,
			'properties' => [
				'title' => $this->textValueFromDefinition($descriptor['title'] ?? ''),
				'description' => $this->textValueFromDefinition($descriptor['description'] ?? ''),
				'submit_label' => $this->textValueFromDefinition($descriptor['submit_label'] ?? ''),
			],
			'usage' => $this->usageForDefinition($definition_slug),
			'can_undo' => $cursor > 1 && $this->editHistorySeqExists($definition_id, $session_token, $cursor - 1),
			'can_redo' => $cursor > 0 && $this->editHistorySeqExists($definition_id, $session_token, $cursor + 1),
		];
	}

	/**
	 * Editable text of a descriptor text definition: keyed mode stores {key, text},
	 * literal mode a plain string. A key-only definition (e.g. the default submit
	 * label's {key: form.capture.submit}) resolves through the translation catalog so
	 * the editor shows the rendered fallback instead of a blank field that would
	 * overwrite the default on save.
	 */
	private function textValueFromDefinition(mixed $definition): string
	{
		if (!is_array($definition)) {
			return is_string($definition) ? $definition : '';
		}

		$text = (string)($definition['text'] ?? '');

		if ($text !== '') {
			return $text;
		}

		$key = is_string($definition['key'] ?? null) ? trim((string)$definition['key']) : '';

		if ($key === '') {
			return '';
		}

		$params = is_array($definition['params'] ?? null) ? $definition['params'] : [];
		$resolved = t($key, $params);

		return $resolved !== $key ? $resolved : '';
	}

	/**
	 * Drop editing sessions (and their history rings) that have been idle for a week.
	 * Called opportunistically when an editor opens; no scheduler needed.
	 */
	public function purgeStaleEditorSessions(): void
	{
		if (!$this->tableExists('form_editor_sessions')) {
			return;
		}

		DbHelper::prexecute(
			'DELETE h FROM form_definition_edit_history h
				INNER JOIN form_editor_sessions s
					ON s.session_token = h.session_token AND s.definition_id = h.definition_id
				WHERE s.updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)',
		);
		DbHelper::prexecute(
			'DELETE FROM form_editor_sessions WHERE updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)',
		);
	}

	/**
	 * Records the edit order for server-backed, session-scoped undo/redo. The versions
	 * table dedupes states by descriptor hash, so it cannot represent the sequence
	 * A -> B -> A; this ring can. Mutations truncate the redo tail, and the ring is
	 * capped. Outside an editing session (no token) nothing is recorded.
	 *
	 * @param array<string, mixed> $baseline_descriptor descriptor before the mutation
	 * @param array<string, mixed> $save_result return value of saveDraft()
	 * @param string|null $session_token null reads the request's editing-session token
	 * @param int $baseline_version_id version row that held the baseline state, when known
	 * @return array{}|array{can_undo: bool, can_redo: bool}
	 */
	private function recordEditHistory(EntityFormDefinition $definition, array $baseline_descriptor, array $save_result, ?string $session_token = null, int $baseline_version_id = 0): array
	{
		$session_token ??= CmsConfig::editorSessionToken();

		if ($session_token === '' || ($save_result['status'] ?? '') === 'conflict') {
			return [];
		}

		$definition_id = (int)$definition->definition_id;
		$saved_descriptor = is_array($save_result['descriptor'] ?? null) ? $save_result['descriptor'] : null;

		if ($saved_descriptor === null) {
			return [];
		}

		$saved_version_id = (int)($save_result['draft_version_id']
			?? $save_result['matched_version_id']
			?? $save_result['active_draft']['version_id']
			?? $save_result['published_version']['version_id']
			?? 0);
		$cursor = $this->editHistoryCursor($definition_id, $session_token);

		if ($cursor === 0) {
			DbHelper::prexecute(
				'INSERT INTO form_definition_edit_history (definition_id, session_token, version_id, seq, descriptor_json) VALUES (?, ?, ?, ?, ?)',
				[$definition_id, $session_token, $baseline_version_id > 0 ? $baseline_version_id : null, 1, json_encode($baseline_descriptor, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
			);
			$cursor = 1;
		}

		DbHelper::prexecute(
			'DELETE FROM form_definition_edit_history WHERE definition_id = ? AND session_token = ? AND seq > ?',
			[$definition_id, $session_token, $cursor],
		);
		DbHelper::prexecute(
			'INSERT INTO form_definition_edit_history (definition_id, session_token, version_id, seq, descriptor_json) VALUES (?, ?, ?, ?, ?)',
			[$definition_id, $session_token, $saved_version_id > 0 ? $saved_version_id : null, $cursor + 1, json_encode($saved_descriptor, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
		);
		$this->setEditHistoryCursor($definition_id, $session_token, $cursor + 1);
		DbHelper::prexecute(
			'DELETE FROM form_definition_edit_history WHERE definition_id = ? AND session_token = ? AND seq <= ?',
			[$definition_id, $session_token, $cursor + 1 - self::EDIT_HISTORY_LIMIT],
		);

		return [
			'can_undo' => true,
			'can_redo' => false,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function applyEditHistoryStep(string $definition_slug, int $direction, string $session_token): array
	{
		if ($session_token === '') {
			throw new InvalidArgumentException('Edit history requires an editing session.');
		}

		$definition = $this->requireDbDefinition($definition_slug);
		$definition_id = (int)$definition->definition_id;
		$cursor = $this->editHistoryCursor($definition_id, $session_token);
		$target_seq = $cursor + $direction;
		$entry = DbHelper::selectOneFromQuery(
			'SELECT descriptor_json, version_id FROM form_definition_edit_history WHERE definition_id = ? AND session_token = ? AND seq = ?',
			[$definition_id, $session_token, $target_seq],
		);
		$descriptor_json = is_array($entry) ? (string)($entry['descriptor_json'] ?? '') : '';

		if ($descriptor_json === '') {
			throw new InvalidArgumentException($direction < 0 ? 'Nothing to undo.' : 'Nothing to redo.');
		}

		$descriptor = json_decode($descriptor_json, true, 512, JSON_THROW_ON_ERROR);

		if (!is_array($descriptor)) {
			throw new UnexpectedValueException('Stored edit-history descriptor is invalid.');
		}

		// Steps linked to a still-existing version row re-activate that row, so undo
		// never duplicates versions; descriptor replay through the autosave path is
		// the fallback for unlinked or since-deleted rows.
		$linked_version = ((int)($entry['version_id'] ?? 0)) > 0
			? EntityFormDefinitionVersion::findFirst([
				'definition_id' => $definition_id,
				'version_id' => (int)$entry['version_id'],
			])
			: null;

		if ($linked_version instanceof EntityFormDefinitionVersion) {
			$this->activateVersionRow($definition, $linked_version, $this->descriptorFromVersion($definition, $linked_version));
			$result = $this->loadDefinition($definition_slug);
		} else {
			$result = $this->saveDraft($definition_slug, $descriptor, '', true);
		}

		$this->setEditHistoryCursor($definition_id, $session_token, $target_seq);

		return $result + [
			'action' => $direction < 0 ? 'undo' : 'redo',
			'edit_history_seq' => $target_seq,
			'can_undo' => $this->editHistorySeqExists($definition_id, $session_token, $target_seq - 1),
			'can_redo' => $this->editHistorySeqExists($definition_id, $session_token, $target_seq + 1),
		];
	}

	private function editHistoryCursor(int $definition_id, string $session_token): int
	{
		return (int)DbHelper::selectOneColumnFromQuery(
			'SELECT edit_cursor FROM form_editor_sessions WHERE session_token = ? AND definition_id = ?',
			[$session_token, $definition_id],
		);
	}

	private function setEditHistoryCursor(int $definition_id, string $session_token, int $seq): void
	{
		DbHelper::prexecute(
			'INSERT INTO form_editor_sessions (session_token, definition_id, edit_cursor) VALUES (?, ?, ?)
				ON DUPLICATE KEY UPDATE edit_cursor = VALUES(edit_cursor), updated_at = CURRENT_TIMESTAMP',
			[$session_token, $definition_id, $seq],
		);
	}

	private function editHistorySeqExists(int $definition_id, string $session_token, int $seq): bool
	{
		return (int)DbHelper::selectOneColumnFromQuery(
			'SELECT COUNT(*) FROM form_definition_edit_history WHERE definition_id = ? AND session_token = ? AND seq = ?',
			[$definition_id, $session_token, $seq],
		) > 0;
	}

	/**
	 * Keeps definition-owned keyed text definitions ({key, text}) keyed while updating
	 * the default text. Values keyed to a SHARED catalog key (e.g. the default submit
	 * label's form.capture.submit) must not keep that key, or the rendered text keeps
	 * resolving the shared translation and the edit never shows: keyed-mode forms re-key
	 * to the definition's own key, literal-mode forms fall back to a plain string.
	 *
	 * @param array<string, mixed> $descriptor
	 */
	private function textDefinitionForDescriptor(array $descriptor, string $key, string $text, string $definition_slug): array|string
	{
		$current = $descriptor[$key] ?? null;

		if (is_array($current) && isset($current['key']) && is_string($current['key']) && trim($current['key']) !== '') {
			$own_prefix = FormCaptureDescriptorSchemaValidator::i18nKeyPrefixForDefinition($definition_slug) . '.';

			if (str_starts_with(trim($current['key']), $own_prefix)) {
				return array_replace($current, ['text' => $text]);
			}

			if (($descriptor['i18n_mode'] ?? FormCaptureDescriptorSchemaValidator::I18N_MODE_LITERAL) === FormCaptureDescriptorSchemaValidator::I18N_MODE_KEYED) {
				return ['key' => $own_prefix . $key, 'text' => $text];
			}

			return $text;
		}

		return $text;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function publishDraft(string $definition_slug, ?int $version_id = null): array
	{
		$definition = $this->requireDbDefinition($definition_slug);
		$active_draft = $this->findActiveDraft((int)$definition->definition_id);
		$version = $version_id !== null
			? EntityFormDefinitionVersion::findFirst([
				'definition_id' => (int)$definition->definition_id,
				'version_id' => $version_id,
				'status' => self::STATUS_DRAFT,
			])
			: $active_draft;

		if ($version_id !== null && (!$version instanceof EntityFormDefinitionVersion || !$active_draft instanceof EntityFormDefinitionVersion || (int)$version->version_id !== (int)$active_draft->version_id)) {
			throw new InvalidArgumentException('Only the active draft version can be published.');
		}

		if (!$version instanceof EntityFormDefinitionVersion) {
			$published = $this->findPublishedVersion($definition);

			if ($published instanceof EntityFormDefinitionVersion) {
				return $this->loadDefinition($definition_slug) + [
					'action' => 'already_published',
				];
			}

			throw new InvalidArgumentException('No draft version is available to publish.');
		}

		$descriptor = $this->descriptorFromVersion($definition, $version);
		$security = $this->securityForDefinition($definition, $descriptor);
		$pdo = Db::instance();
		$started_transaction = !$pdo->inTransaction();
		$cache = new FormCaptureCompiledDescriptorCache();

		try {
			if ($started_transaction) {
				$pdo->beginTransaction();
			}

			$this->syncDescriptorI18nRows($definition_slug, $descriptor);
			$version = EntityFormDefinitionVersion::updateById((int)$version->version_id, [
				'status' => self::STATUS_PUBLISHED,
				'published_at' => date('Y-m-d H:i:s'),
			]);
			$cache->write($definition->dto(), $version->dto(), $descriptor, $security);
			EntityFormDefinition::updateById((int)$definition->definition_id, [
				'status' => self::STATUS_PUBLISHED,
				'published_version_id' => (int)$version->version_id,
			]);

			if ($started_transaction) {
				$pdo->commit();
			}
		} catch (Throwable $exception) {
			if ($started_transaction && $pdo->inTransaction()) {
				$pdo->rollBack();
			}

			throw $exception;
		}

		try {
			$cache->deleteStaleForSlug($definition_slug, (int)$version->version_number);
		} catch (Throwable $exception) {
			error_log('[form-builder] failed to delete stale descriptor cache: ' . $exception->getMessage());
		}

		return $this->loadDefinition($definition_slug) + [
			'action' => 'published',
			'published_version_id' => (int)$version->version_id,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function defaultDescriptor(string $definition_slug, ?string $title = null): array
	{
		return [
			'kind' => 'capture',
			'i18n_mode' => FormCaptureDescriptorSchemaValidator::I18N_MODE_LITERAL,
			'title' => ['text' => $title ?? $definition_slug],
			'description' => ['text' => ''],
			'submit_label' => ['key' => 'form.capture.submit'],
			'fields' => [
				[
					'type' => 'text',
					'name' => 'name',
					'key' => 'name',
					'label' => ['text' => t('form.builder.field.text')],
					'normalizers' => ['trim'],
					'validators' => [
						['type' => 'required'],
					],
				],
			],
		];
	}

	private function requireDbDefinition(string $definition_slug): EntityFormDefinition
	{
		$this->assertTablesInstalled();
		$definition = EntityFormDefinition::findBySlug($definition_slug);

		if (!$definition instanceof EntityFormDefinition || (string)$definition->kind !== 'capture') {
			throw new InvalidArgumentException('Capture form definition does not exist.');
		}

		if ((string)$definition->source !== self::SOURCE_DB) {
			throw new InvalidArgumentException('Shipped capture form definitions are read-only in the builder.');
		}

		return $definition;
	}

	private function findActiveDraft(int $definition_id): ?EntityFormDefinitionVersion
	{
		return EntityFormDefinitionVersion::findFirst(
			['definition_id' => $definition_id, 'status' => self::STATUS_DRAFT],
			'version_number DESC, version_id DESC',
		);
	}

	private function findPublishedVersion(EntityFormDefinition $definition): ?EntityFormDefinitionVersion
	{
		$version_id = (int)($definition->published_version_id ?? 0);

		return $version_id > 0 ? EntityFormDefinitionVersion::findPublishedForDefinition((int)$definition->definition_id, $version_id) : null;
	}

	private function findPublishedVersionByHash(int $definition_id, string $descriptor_hash): ?EntityFormDefinitionVersion
	{
		return EntityFormDefinitionVersion::findFirst([
			'definition_id' => $definition_id,
			'descriptor_hash' => $descriptor_hash,
			'status' => self::STATUS_PUBLISHED,
		]);
	}

	private function currentServerHash(EntityFormDefinition $definition): string
	{
		$version = $this->findActiveDraft((int)$definition->definition_id) ?? $this->findPublishedVersion($definition);

		return $version instanceof EntityFormDefinitionVersion ? (string)$version->descriptor_hash : '';
	}

	/**
	 * @param array<string, mixed> $descriptor
	 * @return array<string, mixed>
	 */
	private function newFieldDescriptorFromPalette(string $definition_slug, array $descriptor, string $field_type): array
	{
		$item = $this->findPaletteItem($field_type);

		if (!$item instanceof EditorPaletteItem) {
			throw new InvalidArgumentException('The selected form element is not available.');
		}

		$field = $item->defaults;
		$field['type'] = $item->type;
		$field[FormCaptureFieldIdentity::DESCRIPTOR_KEY] = FormCaptureFieldIdentity::generateUid($this->existingFieldUids($descriptor));
		$default_name = trim((string)($field['name'] ?? $item->type));
		$default_key = trim((string)($field['key'] ?? $default_name));
		$existing_names = [];
		$existing_keys = [];

		foreach (($descriptor['fields'] ?? []) as $existing_field) {
			if (!is_array($existing_field)) {
				continue;
			}

			$name = trim((string)($existing_field['name'] ?? ''));
			$key = trim((string)($existing_field['key'] ?? $name));

			if ($name !== '') {
				$existing_names[$name] = true;
			}

			if ($key !== '') {
				$existing_keys[$key] = true;
			}
		}

		$field['name'] = $this->uniqueFieldIdentifier($default_name !== '' ? $default_name : $item->type, $existing_names);
		$key_base = $default_key === $default_name ? (string)$field['name'] : ($default_key !== '' ? $default_key : (string)$field['name']);
		$field['key'] = $this->uniqueFieldIdentifier($key_base, $existing_keys);

		if (($descriptor['i18n_mode'] ?? FormCaptureDescriptorSchemaValidator::I18N_MODE_LITERAL) === FormCaptureDescriptorSchemaValidator::I18N_MODE_KEYED) {
			$field = $this->applyKeyedI18nDefaults($definition_slug, $field, $item->label);
		}

		return $field;
	}

	/**
	 * @param array<string, mixed> $field
	 * @return array<string, mixed>
	 */
	private function applyKeyedI18nDefaults(string $definition_slug, array $field, string $label_text): array
	{
		$field_key = (string)$field['key'];
		$key_prefix = FormCaptureDescriptorSchemaValidator::i18nKeyPrefixForDefinition($definition_slug)
			. '.fields.' . $field_key;
		$label = is_array($field['label'] ?? null) ? $field['label'] : ['text' => $label_text];
		$label['text'] = (string)($label['text'] ?? $label_text);
		$label['key'] = $key_prefix . '.label';
		$field['label'] = $label;

		if (is_array($field['values'] ?? null)) {
			foreach ($field['values'] as $index => $option) {
				if (!is_array($option)) {
					continue;
				}

				$option_segment = $this->i18nKeySegment((string)($option['value'] ?? 'option_' . ((int)$index + 1)));
				$option_label = is_array($option['label'] ?? null) ? $option['label'] : [
					'text' => (string)($option['label'] ?? $option['value'] ?? $option_segment),
				];
				$option_label['text'] = (string)($option_label['text'] ?? $option['value'] ?? $option_segment);
				$option_label['key'] = $key_prefix . '.options.' . $option_segment . '.label';
				$option['label'] = $option_label;
				$field['values'][$index] = $option;
			}
		}

		return $field;
	}

	private function findPaletteItem(string $field_type): ?EditorPaletteItem
	{
		$field_type = trim($field_type);
		$provider = new FormCaptureEditorPaletteProvider();

		foreach ($provider->getPaletteItems() as $item) {
			if ($item->type === $field_type && in_array(FormCaptureEditorPaletteProvider::TARGET_FIELDS, $item->dropTargetIds, true)) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * @param array<string, true> $existing
	 */
	private function uniqueFieldIdentifier(string $base, array $existing): string
	{
		$base = strtolower(trim($base));
		$base = (string)preg_replace('/[^a-z0-9_]+/', '_', $base);
		$base = trim($base, '_');

		if ($base === '' || preg_match('/^[a-z]/', $base) !== 1) {
			$base = 'field_' . $base;
		}

		$candidate = $base;
		$suffix = 2;

		while (isset($existing[$candidate])) {
			$candidate = $base . '_' . $suffix;
			++$suffix;
		}

		return $candidate;
	}

	private function i18nKeySegment(string $value): string
	{
		$segment = strtolower(trim($value));
		$segment = (string)preg_replace('/[^a-z0-9_]+/', '_', $segment);
		$segment = trim($segment, '_');

		return $segment !== '' && preg_match('/^[a-z]/', $segment) === 1 ? $segment : 'option';
	}

	/**
	 * @param list<array<string, mixed>> $fields
	 * @param array<string, mixed> $field
	 * @return list<array<string, mixed>>
	 */
	private function insertFieldAtVisibleIndex(array $fields, array $field, int $insert_index): array
	{
		$insert_index = max(0, $insert_index);
		$visible_index = 0;

		foreach ($fields as $offset => $existing_field) {
			if (!is_array($existing_field) || (string)($existing_field['type'] ?? '') === 'hidden') {
				continue;
			}

			if ($visible_index === $insert_index) {
				array_splice($fields, $offset, 0, [$field]);

				return array_values($fields);
			}

			++$visible_index;
		}

		$fields[] = $field;

		return array_values($fields);
	}

	/**
	 * @param array<string, mixed> $descriptor
	 * @return list<array<string, mixed>>
	 */
	private function normalizedDescriptorFields(array $descriptor): array
	{
		$fields = [];

		foreach (($descriptor['fields'] ?? []) as $field) {
			if (is_array($field)) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	/**
	 * @param list<array<string, mixed>> $fields
	 * @return list<int>
	 */
	private function visibleFieldOffsets(array $fields): array
	{
		$offsets = [];

		foreach ($fields as $offset => $field) {
			if ((string)($field['type'] ?? '') !== 'hidden') {
				$offsets[] = (int)$offset;
			}
		}

		return $offsets;
	}

	/**
	 * @param list<array<string, mixed>> $fields
	 */
	private function assertVisibleFieldOffset(array $fields, int $field_offset): void
	{
		if (!isset($fields[$field_offset]) || (string)($fields[$field_offset]['type'] ?? '') === 'hidden') {
			throw new InvalidArgumentException('Only visible capture form fields can be edited from edit mode.');
		}
	}

	/**
	 * @param array<string, mixed> $descriptor
	 * @return array<string, true>
	 */
	private function existingFieldUids(array $descriptor): array
	{
		$existing = [];

		foreach (($descriptor['fields'] ?? []) as $field) {
			if (!is_array($field)) {
				continue;
			}

			$uid = FormCaptureFieldIdentity::normalizeUid($field[FormCaptureFieldIdentity::DESCRIPTOR_KEY] ?? '');

			if ($uid !== '') {
				$existing[$uid] = true;
			}
		}

		return $existing;
	}

	/**
	 * @param list<array<string, mixed>> $fields
	 */
	private function findFieldOffset(array $fields, string $field_uid, string $field_key, int $field_index): int
	{
		$field_uid = FormCaptureFieldIdentity::normalizeUid($field_uid);

		if ($field_uid !== '') {
			foreach ($fields as $offset => $field) {
				if (!is_array($field)) {
					continue;
				}

				if ($field_uid === FormCaptureFieldIdentity::normalizeUid($field[FormCaptureFieldIdentity::DESCRIPTOR_KEY] ?? '')) {
					return $offset;
				}
			}
		}

		$field_key = trim($field_key);

		if ($field_key !== '') {
			foreach ($fields as $offset => $field) {
				if (!is_array($field)) {
					continue;
				}

				$key = trim((string)($field['key'] ?? $field['name'] ?? ''));
				$name = trim((string)($field['name'] ?? ''));

				if ($field_key === $key || $field_key === $name) {
					return $offset;
				}
			}
		}

		if ($field_index >= 0 && isset($fields[$field_index]) && is_array($fields[$field_index])) {
			return $field_index;
		}

		throw new InvalidArgumentException('Capture form field does not exist.');
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function versionsForDefinition(int $definition_id): array
	{
		$rows = DbHelper::selectManyFromQuery(
			"SELECT
				version_id,
				definition_id,
				version_number,
				status,
				descriptor_hash,
				author_note,
				created_at,
				published_at
			FROM form_definition_versions
			WHERE definition_id = ?
			ORDER BY version_number DESC, version_id DESC",
			[$definition_id],
		);

		return array_map(static fn (array $row): array => [
			'version_id' => (int)$row['version_id'],
			'definition_id' => (int)$row['definition_id'],
			'version_number' => (int)$row['version_number'],
			'status' => (string)$row['status'],
			'descriptor_hash' => (string)$row['descriptor_hash'],
			'author_note' => $row['author_note'] === null ? '' : (string)$row['author_note'],
			'created_at' => $row['created_at'] === null ? null : (string)$row['created_at'],
			'published_at' => $row['published_at'] === null ? null : (string)$row['published_at'],
		], $rows);
	}

	private function abandonDrafts(int $definition_id): void
	{
		DbHelper::prexecute(
			"UPDATE form_definition_versions
			SET status = ?
			WHERE definition_id = ?
			  AND status = ?",
			[self::STATUS_ABANDONED, $definition_id, self::STATUS_DRAFT],
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function descriptorFromVersion(EntityFormDefinition $definition, EntityFormDefinitionVersion $version): array
	{
		if (!hash_equals((string)$version->descriptor_hash, hash('sha256', (string)$version->descriptor_json))) {
			throw new InvalidArgumentException('Capture form descriptor_hash does not match descriptor_json.');
		}

		$descriptor = FormCaptureDescriptorSchemaValidator::normalizeDescriptor(
			$this->decodeJsonObject((string)$version->descriptor_json, 'descriptor_json'),
		);
		$security = $this->securityForDefinition($definition, $descriptor);
		FormCaptureDescriptorSchemaValidator::validateForDefinition((string)$definition->definition_slug, $descriptor, $security);

		return $descriptor;
	}

	/**
	 * @param array<string, mixed> $descriptor
	 * @return array<string, mixed>
	 */
	private function securityForDefinition(EntityFormDefinition $definition, array $descriptor): array
	{
		$field_keys = FormCaptureDescriptorSchemaValidator::validateDescriptor($descriptor);

		return FormCaptureDescriptorSchemaValidator::normalizeSecurity((string)$definition->security_json, $field_keys);
	}

	/**
	 * @param array<string, mixed> $descriptor
	 * @param array<string, mixed>|string|null $security
	 * @return array{descriptor: array<string, mixed>, security: array<string, mixed>, descriptor_json: string, descriptor_hash: string, security_json: string}
	 */
	private function prepareDescriptor(string $definition_slug, array $descriptor, array|string|null $security): array
	{
		FormCaptureDescriptorSchemaValidator::validateDefinitionSlug($definition_slug);
		$normalized_descriptor = FormCaptureDescriptorSchemaValidator::normalizeDescriptor($descriptor);
		$field_keys = FormCaptureDescriptorSchemaValidator::validateDescriptor($normalized_descriptor);
		$normalized_security = FormCaptureDescriptorSchemaValidator::normalizeSecurity($security, $field_keys);
		FormCaptureDescriptorSchemaValidator::validateForDefinition($definition_slug, $normalized_descriptor, $normalized_security);
		$descriptor_json = FormCaptureCompiledDescriptorCache::encodeJson($normalized_descriptor);

		return [
			'descriptor' => $normalized_descriptor,
			'security' => $normalized_security,
			'descriptor_json' => $descriptor_json,
			'descriptor_hash' => hash('sha256', $descriptor_json),
			'security_json' => FormCaptureCompiledDescriptorCache::encodeJson($normalized_security),
		];
	}

	/**
	 * @param array<string, mixed> $descriptor
	 */
	private function syncDescriptorI18nRows(string $definition_slug, array $descriptor): void
	{
		if (($descriptor['i18n_mode'] ?? FormCaptureDescriptorSchemaValidator::I18N_MODE_LITERAL) !== FormCaptureDescriptorSchemaValidator::I18N_MODE_KEYED) {
			return;
		}

		if (!$this->tableExists('i18n_messages') || !$this->tableExists('i18n_translations')) {
			return;
		}

		$default_locale = LocaleService::getDefaultLocale();
		$locales = array_values(array_unique(array_merge(
			[$default_locale],
			array_map('strval', I18nRuntime::getAvailableLocaleCodes()),
		)));

		foreach (FormCaptureDescriptorSchemaValidator::extractI18nReferences($descriptor) as $reference) {
			$text = trim($reference['text']);

			if ($text === '') {
				continue;
			}

			try {
				[$domain, $key] = $this->splitI18nKey($reference['key']);
			} catch (InvalidArgumentException $exception) {
				Kernel::logException($exception, 'Skipping invalid capture form i18n reference during builder sync', [
					'definition_slug' => $definition_slug,
					'path' => $reference['path'] ?? '',
				]);

				continue;
			}

			$default_translation = $this->findI18nTranslation($domain, $key, '', $default_locale);
			$default_text = $text;

			if (
				$default_translation instanceof EntityI18n_translation
				&& (bool)(int)$default_translation->human_reviewed
				&& trim((string)$default_translation->text) !== ''
			) {
				$default_text = (string)$default_translation->text;
			}

			I18nTranslationService::saveTranslation(
				$domain,
				$key,
				'',
				$default_locale,
				$default_text,
				true,
				false,
				$text,
				false,
			);

			foreach ($locales as $locale) {
				if ($locale === $default_locale || $this->findI18nTranslation($domain, $key, '', $locale) instanceof EntityI18n_translation) {
					continue;
				}

				I18nTranslationService::saveTranslation(
					$domain,
					$key,
					'',
					$locale,
					$text,
					false,
					false,
					$text,
					true,
				);
			}
		}

		// Catalogs are build artifacts, so keyed-label edits normally go live on the
		// next i18n:build. Editor mutations rebuild the editing admin's locale inline
		// so the canvas reflects the edit immediately.
		if (CmsConfig::editorSessionToken() !== '') {
			try {
				I18nCatalogBuilder::build(Kernel::getLocale());
				// The fragment render later in this request must see the new file
				// mtime, or the runtime keeps serving the cached catalog.
				clearstatcache();
			} catch (Throwable $exception) {
				Kernel::logException($exception, 'Editor i18n catalog rebuild failed', [
					'definition_slug' => $definition_slug,
				]);
			}
		}
	}

	private function findI18nTranslation(string $domain, string $key, string $context, string $locale): ?EntityI18n_translation
	{
		$translation = EntityI18n_translation::findById([
			'domain' => $domain,
			'key' => $key,
			'context' => $context,
			'locale' => $locale,
		]);

		return $translation instanceof EntityI18n_translation ? $translation : null;
	}

	/**
	 * @return array{string, string}
	 */
	private function splitI18nKey(string $key): array
	{
		$parts = explode('.', $key, 2);

		if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
			throw new InvalidArgumentException("Invalid form i18n key '{$key}'.");
		}

		return [$parts[0], $parts[1]];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function decodeJsonObject(string $json, string $label): array
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

	/**
	 * @param array<string, mixed> $descriptor
	 * @return array<string, mixed>
	 */
	private function descriptorForBuilder(array $descriptor, bool $resolve_text_fallbacks): array
	{
		if (!$resolve_text_fallbacks) {
			return $descriptor;
		}

		return $this->withResolvedTextFallbacks($descriptor);
	}

	/**
	 * @param array<int|string, mixed> $value
	 * @return array<int|string, mixed>
	 */
	private function withResolvedTextFallbacks(array $value): array
	{
		$key = $value['key'] ?? null;
		$is_text_definition = is_string($key)
			&& trim($key) !== ''
			&& array_diff(array_keys($value), ['text', 'key', 'params']) === [];

		if ($is_text_definition && !array_key_exists('text', $value)) {
			$params = is_array($value['params'] ?? null) ? $value['params'] : [];
			$text = t(trim($key), $params);

			if ($text !== trim($key)) {
				$value['text'] = $text;
			}
		}

		foreach ($value as $child_key => $child) {
			if (is_array($child)) {
				$value[$child_key] = $this->withResolvedTextFallbacks($child);
			}
		}

		return $value;
	}

	/**
	 * @param array<string, mixed>|null $descriptor
	 */
	private function translationUrlForDefinition(string $definition_slug, ?array $descriptor = null): string
	{
		$definition_slug = trim($definition_slug);
		$query = $descriptor !== null ? $this->translationFilterForDescriptor($descriptor) : null;

		if ($query === null) {
			$query = [
				'domain' => FormCaptureDescriptorSchemaValidator::FORM_I18N_DOMAIN,
			];

			if ($definition_slug !== '') {
				try {
					$query['search'] = FormCaptureDescriptorSchemaValidator::i18nSlugForDefinition($definition_slug) . '.';
				} catch (Throwable) {
				}
			}
		}

		return $this->i18nWorkbenchUrl() . '?' . http_build_query($query);
	}

	private function i18nWorkbenchUrl(): string
	{
		$path = WidgetI18nWorkbench::getDefaultPathForCreation()['path'] ?? '/admin/i18n/';

		return (string)$path;
	}

	/**
	 * @param array<string, mixed> $descriptor
	 * @return array{domain: string, search?: string}|null
	 */
	private function translationFilterForDescriptor(array $descriptor): ?array
	{
		$groups = [];

		foreach (FormCaptureDescriptorSchemaValidator::extractI18nReferences($descriptor) as $reference) {
			$parts = explode('.', $reference['key'], 3);

			if (count($parts) < 3 || $parts[0] === '' || $parts[1] === '') {
				continue;
			}

			$group_key = $parts[0] . "\0" . $parts[1];
			$groups[$group_key] = ($groups[$group_key] ?? 0) + 1;
		}

		if ($groups === []) {
			return null;
		}

		uksort($groups, static function (string $left, string $right) use ($groups): int {
			$count_compare = $groups[$right] <=> $groups[$left];

			if ($count_compare !== 0) {
				return $count_compare;
			}

			[$left_domain, $left_prefix] = explode("\0", $left, 2);
			[$right_domain, $right_prefix] = explode("\0", $right, 2);
			$domain_compare = ($right_domain === FormCaptureDescriptorSchemaValidator::FORM_I18N_DOMAIN ? 1 : 0)
				<=> ($left_domain === FormCaptureDescriptorSchemaValidator::FORM_I18N_DOMAIN ? 1 : 0);

			if ($domain_compare !== 0) {
				return $domain_compare;
			}

			return strlen($right_prefix) <=> strlen($left_prefix);
		});

		[$domain, $prefix] = explode("\0", array_key_first($groups), 2);

		return [
			'domain' => $domain,
			'search' => $prefix . '.',
		];
	}

	private function assertTablesInstalled(): void
	{
		if (!$this->tablesInstalled()) {
			throw new RuntimeException('Capture form definition tables are not installed.');
		}
	}

	private function tablesInstalled(): bool
	{
		return $this->tableExists('form_definitions') && $this->tableExists('form_definition_versions');
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

	/**
	 * @return array<string, int>
	 */
	private function usageCountsByDefinition(): array
	{
		if (!$this->tableExists('widget_connections') || !$this->tableExists('attributes')) {
			return [];
		}

		$rows = DbHelper::selectManyFromQuery(
			"SELECT
				a.param_value AS definition_slug,
				wc.page_id
			FROM widget_connections wc
			INNER JOIN attributes a
				ON a.resource_name = ?
			   AND a.resource_id = wc.connection_id
			   AND a.param_name = 'definition_slug'
			WHERE wc.widget_name = ?",
			[
				ResourceNames::WIDGET_CONNECTION,
				$this->captureFormWidgetName(),
			],
		);

		$counts = [];

		foreach ($rows as $row) {
			$definition_slug = (string)($row['definition_slug'] ?? '');
			$page_id = (int)($row['page_id'] ?? 0);

			if ($definition_slug === '' || $page_id <= 0 || !ResourceAcl::canAccessResource($page_id, ResourceAcl::_ACL_VIEW)) {
				continue;
			}

			$counts[$definition_slug] = ($counts[$definition_slug] ?? 0) + 1;
		}

		return $counts;
	}

	private function captureFormWidgetName(): string
	{
		$constant = WidgetList::class . '::CAPTUREFORM';

		return defined($constant) ? (string)constant($constant) : 'CaptureForm';
	}
}
