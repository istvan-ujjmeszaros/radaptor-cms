<?php

declare(strict_types=1);

final class FormCaptureAuthoringService
{
	private const string SOURCE_DB = 'db';
	private const string SOURCE_SHIPPED = 'shipped';
	private const string STATUS_DRAFT = 'draft';
	private const string STATUS_ABANDONED = 'abandoned';
	private const string STATUS_PUBLISHED = 'published';

	/**
	 * @return array<string, mixed>
	 */
	public function buildBuilderState(?string $definition_slug = null): array
	{
		$definition_slug = trim((string)$definition_slug);
		$definitions = $this->listDefinitions();
		$selected = $definition_slug !== '' ? $definition_slug : (string)($definitions[0]['definition_slug'] ?? '');
		$state = $selected !== '' ? $this->loadDefinition($selected) : $this->emptyDefinitionState();
		$provider = new FormCaptureEditorPaletteProvider();

		return [
			'definitions' => $definitions,
			'selected' => $state,
			'palette' => array_map(static fn (EditorPaletteItem $item): array => $item->toArray(), $provider->getPaletteItems()),
			'drop_targets' => array_map(static fn (EditorDropTarget $target): array => $target->toArray(), $provider->getDropTargets()),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function buildBuilderTree(?string $definition_slug = null, string $panel = 'properties'): array
	{
		$state = $this->buildBuilderState($definition_slug);
		$selected = is_array($state['selected'] ?? null) ? $state['selected'] : [];
		$descriptor = is_array($selected['descriptor'] ?? null) ? $selected['descriptor'] : [];
		$preview = $this->renderPreview(
			(string)($selected['definition']['definition_slug'] ?? 'capture-preview'),
			$descriptor,
		);

		return SduiNode::create(
			component: 'captureFormBuilder',
			props: [
				'state' => $state,
				'initial_panel' => $panel === 'usage' ? 'usage' : 'properties',
				'initial_preview' => $preview,
				'initial_preview_html' => $preview['html'] ?? '',
				'csrf_token' => FormSubmitContext::issueCsrfTokenForForm(FormBuilderEventHelper::CSRF_FORM_ID),
				'urls' => [
					'create' => Url::getUrl('form_builder.create'),
					'preview_render' => Url::getUrl('form_builder.preview_render'),
					'save_draft' => Url::getUrl('form_builder.save_draft'),
					'publish' => Url::getUrl('form_builder.publish'),
				],
			],
			type: SduiNode::TYPE_WIDGET,
			strings: WidgetCaptureFormBuilder::buildStrings(),
		);
	}

	public function renderBuilderFragment(?string $definition_slug = null, string $panel = 'properties'): string
	{
		$renderer = new HtmlTreeRenderer(theme: $this->currentAdminTheme(), lang_id: Kernel::getLocale(), is_editable: false);

		return $renderer->render($this->buildBuilderTree($definition_slug, $panel));
	}

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
		$security = $this->securityForDefinition($definition, $descriptor);

		return [
			'definition' => $definition->dto(),
			'descriptor' => $descriptor,
			'security' => $security,
			'active_draft' => $active_draft instanceof EntityFormDefinitionVersion ? $active_draft->dto() : null,
			'published_version' => $published instanceof EntityFormDefinitionVersion ? $published->dto() : null,
			'versions' => $this->versionsForDefinition((int)$definition->definition_id),
			'usage' => $this->usageForDefinition($definition_slug),
			'base_server_hash' => $selected_version instanceof EntityFormDefinitionVersion ? (string)$selected_version->descriptor_hash : '',
			'read_only' => (string)$definition->source !== self::SOURCE_DB,
			'status' => $active_draft instanceof EntityFormDefinitionVersion ? self::STATUS_DRAFT : (string)$definition->status,
		];
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
	 * @param array<string, mixed> $descriptor
	 * @return array<string, mixed>
	 */
	public function renderPreview(string $definition_slug, array $descriptor): array
	{
		$definition_slug = trim($definition_slug) !== '' ? trim($definition_slug) : 'capture-preview';
		$prepared = $this->prepareDescriptor($definition_slug, $descriptor, $this->securityForPreview($definition_slug));
		$resolution = FormDefinitionResolution::capture(
			$definition_slug,
			[
				'definition_id' => 0,
				'definition_slug' => $definition_slug,
				'kind' => 'capture',
				'source' => self::SOURCE_DB,
				'status' => self::STATUS_DRAFT,
				'owner_user_id' => null,
				'security_json' => $prepared['security_json'],
				'published_version_id' => null,
			],
			[
				'version_id' => 1,
				'definition_id' => 0,
				'version_number' => 1,
				'status' => self::STATUS_DRAFT,
				'descriptor_hash' => $prepared['descriptor_hash'],
				'published_at' => null,
			],
			$prepared['descriptor'],
			$prepared['security'],
		);
		$theme = $this->currentAdminTheme();
		$tree_context = new FormCapturePreviewTreeContext($theme);
		$form = new CaptureForm(
			$definition_slug,
			'form_builder_preview',
			$tree_context,
			'',
			[
				'form_definition_resolution' => $resolution,
				'return_target' => '',
				FormSubmitContext::RENDER_CONTEXT_ISSUE_RENDER_STATE => false,
			],
		);
		$renderer = new HtmlTreeRenderer(theme: $theme, lang_id: Kernel::getLocale(), is_editable: false);
		$html = $renderer->render($form->buildTree());

		return [
			'descriptor' => $prepared['descriptor'],
			'descriptor_hash' => $prepared['descriptor_hash'],
			'html' => $html,
			'css' => $renderer->getCss(),
			'js_top' => $renderer->getJsTop(),
			'js' => $renderer->getJs(),
		];
	}

	private function securityForPreview(string $definition_slug): string|null
	{
		if (!$this->tablesInstalled()) {
			return null;
		}

		$definition = EntityFormDefinition::findBySlug($definition_slug);

		if (!$definition instanceof EntityFormDefinition || (string)$definition->kind !== 'capture') {
			return null;
		}

		return (string)$definition->security_json;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function emptyDefinitionState(): array
	{
		$descriptor = $this->defaultDescriptor('capture-new');

		return [
			'definition' => null,
			'descriptor' => $descriptor,
			'security' => FormCaptureDescriptorSchemaValidator::normalizeSecurity(null, ['name']),
			'active_draft' => null,
			'published_version' => null,
			'base_server_hash' => '',
			'read_only' => false,
			'status' => 'new',
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function defaultDescriptor(string $definition_slug, ?string $title = null): array
	{
		return [
			'kind' => 'capture',
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

	private function currentAdminTheme(): ?AbstractThemeData
	{
		$theme_name = Themes::getThemeNameForUser('admin_default');

		return $theme_name !== '' ? ThemeBase::factory($theme_name) : null;
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
