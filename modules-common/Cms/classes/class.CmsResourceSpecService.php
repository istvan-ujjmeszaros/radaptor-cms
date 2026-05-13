<?php

/**
 * @phpstan-type CmsAclPermissions array{
 *     view?: bool,
 *     list?: bool,
 *     create?: bool,
 *     edit?: bool,
 *     delete?: bool,
 *     publish?: bool
 * }
 * @phpstan-type CmsAclSpec array{
 *     inherit?: bool,
 *     usergroups?: array<string, CmsAclPermissions>
 * }
 * @phpstan-type CmsWidgetSpec array{
 *     widget: string,
 *     seq?: int,
 *     attributes?: array<string, scalar|null>,
 *     settings?: array<string, mixed>
 * }
 * @phpstan-type CmsWebpageSpec array{
 *     path: string,
 *     layout?: string,
 *     attributes?: array<string, scalar|null>,
 *     catcher?: bool,
 *     acl?: CmsAclSpec,
 *     slots?: array<string, list<CmsWidgetSpec>>,
 *     replace_slots?: bool
 * }
 * @phpstan-type CmsFolderSpec array{
 *     path: string,
 *     acl?: CmsAclSpec
 * }
 */
class CmsResourceSpecService
{
	private const array ACL_PERMISSION_TO_COLUMN = [
		'view' => 'allow_view',
		'list' => 'allow_list',
		'create' => 'allow_create',
		'edit' => 'allow_edit',
		'delete' => 'allow_delete',
		'publish' => 'allow_publish',
	];

	private const array RESERVED_WEBPAGE_ATTRIBUTE_KEYS = [
		'resource_name' => true,
		'layout' => true,
	];

	/**
	 * @param CmsFolderSpec $spec
	 */
	public static function upsertFolder(array $spec): int
	{
		$path = CmsPathHelper::normalizePath((string) ($spec['path'] ?? '/'));
		$folder = CmsPathHelper::resolveFolder($path);

		if ($folder === null) {
			$folder_id = ResourceTreeHandler::createFolderFromPath($path);

			if (!is_int($folder_id) || $folder_id <= 0) {
				throw new RuntimeException("Unable to create folder {$path}");
			}

			$folder = ResourceTreeHandler::getResourceTreeEntryDataById($folder_id);
		}

		if (!is_array($folder)) {
			throw new RuntimeException("Unable to resolve folder {$path}");
		}

		if (is_array($spec['acl'] ?? null)) {
			self::syncAcl((int) $folder['node_id'], $spec['acl']);
		}

		return (int) $folder['node_id'];
	}

	/**
	 * @param CmsWebpageSpec $spec
	 */
	public static function upsertWebpage(array $spec): int
	{
		$path_parts = CmsPathHelper::splitWebpagePath((string) ($spec['path'] ?? '/'));
		$page = CmsPathHelper::resolveWebpage($path_parts['normalized_path']);

		if ($page === null) {
			$page_id = ResourceTreeHandler::createResourceTreeEntryFromPath(
				$path_parts['folder'],
				$path_parts['resource_name'],
				'webpage',
				isset($spec['layout']) ? (string) $spec['layout'] : null
			);

			if (!is_int($page_id) || $page_id <= 0) {
				throw new RuntimeException("Unable to create webpage {$path_parts['normalized_path']}");
			}

			$page = ResourceTreeHandler::getResourceTreeEntryDataById($page_id);
		}

		if (!is_array($page)) {
			throw new RuntimeException("Unable to resolve webpage {$path_parts['normalized_path']}");
		}

		$update_data = [
			'resource_name' => $path_parts['resource_name'],
		];

		if (isset($spec['layout'])) {
			$update_data['layout'] = (string) $spec['layout'];
		}

		$attributes = (array) ($spec['attributes'] ?? []);
		self::assertWebpageAttributesAllowed($attributes);

		foreach ($attributes as $key => $value) {
			$update_data[(string) $key] = $value;
		}

		$update_result = ResourceTreeHandler::updateResourceTreeEntryResult($update_data, (int) $page['node_id']);

		if (!$update_result->ok) {
			// Resource specs are explicit sync boundaries; callers render this batch failure.
			throw new RuntimeException($update_result->error?->message ?? t('cms.resource.error.update_failed_for_path', ['path' => $path_parts['normalized_path']]));
		}

		if (array_key_exists('catcher', $spec)) {
			if ((bool) $spec['catcher']) {
				ResourceTreeHandler::setAsCatcherPage((int) $page['node_id']);
			} else {
				ResourceTreeHandler::clearCatcherPage((int) $page['node_id']);
			}
		}

		if (is_array($spec['acl'] ?? null)) {
			self::syncAcl((int) $page['node_id'], $spec['acl']);
		}

		if (is_array($spec['slots'] ?? null)) {
			if ((bool) ($spec['replace_slots'] ?? false)) {
				self::syncAllSlots((int) $page['node_id'], $spec['slots']);
			} else {
				self::syncSlots((int) $page['node_id'], $spec['slots']);
			}
		}

		return (int) $page['node_id'];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function exportFolderSpec(string $path): array
	{
		$folder = CmsPathHelper::resolveFolder($path);

		if (!is_array($folder)) {
			throw new RuntimeException("Folder not found: {$path}");
		}

		$export_path = CmsPathHelper::normalizePath($path) === '/' || (string) ($folder['node_type'] ?? '') === 'root'
			? '/'
			: ResourceTreeHandler::getPathFromId((int) $folder['node_id']);

		return [
			'type' => 'folder',
			'path' => $export_path,
			'acl' => self::exportAclSpec((int) $folder['node_id']),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function exportWebpageSpec(string $path): array
	{
		$page = CmsPathHelper::resolveWebpage($path);

		if (!is_array($page)) {
			throw new RuntimeException("Webpage not found: {$path}");
		}

		$page_id = (int) $page['node_id'];
		$attributes = ResourceTypeWebpage::getExtradata($page_id);
		$layout = (string) ($attributes['layout'] ?? '');
		unset($attributes['layout']);

		return [
			'type' => 'webpage',
			'path' => Url::getSeoUrl($page_id, false) ?? ResourceTreeHandler::getPathFromId($page_id),
			'layout' => $layout !== '' ? $layout : null,
			'attributes' => $attributes,
			'catcher' => (bool) ($page['catcher_page'] ?? false),
			'acl' => self::exportAclSpec($page_id),
			'slots' => self::listWidgetsBySlot($page_id, false),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function exportResourceSpec(string $path): array
	{
		$resource = CmsPathHelper::resolveResource($path);

		if (!is_array($resource)) {
			throw new RuntimeException("Resource not found: {$path}");
		}

		if (($resource['node_type'] ?? '') === 'folder' || ($resource['node_type'] ?? '') === 'root') {
			return self::exportFolderSpec($path);
		}

		if (($resource['node_type'] ?? '') !== 'webpage') {
			throw new RuntimeException("Resource type '{$resource['node_type']}' is not exportable yet.");
		}

		return self::exportWebpageSpec($path);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function listResources(string $path = '/'): array
	{
		$resource = CmsPathHelper::resolveFolder($path) ?? CmsPathHelper::resolveResource($path);

		if (!is_array($resource)) {
			throw new RuntimeException("Resource not found: {$path}");
		}

		return ResourceTreeHandler::getResourceChildrenDetailed((int) $resource['node_id']);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function listWidgets(string $path, ?string $slot_name = null): array
	{
		$page = CmsPathHelper::resolveWebpage($path);

		if (!is_array($page)) {
			throw new RuntimeException("Webpage not found: {$path}");
		}

		$slots = self::listWidgetsBySlot((int) $page['node_id']);

		if ($slot_name === null || $slot_name === '') {
			$return = [];

			foreach ($slots as $name => $widgets) {
				foreach ($widgets as $widget) {
					$return[] = ['slot' => $name] + $widget;
				}
			}

			return $return;
		}

		return array_map(
			static fn (array $widget): array => ['slot' => $slot_name] + $widget,
			$slots[$slot_name] ?? []
		);
	}

	/**
	 * @param array<string, scalar|null> $attributes
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	public static function addWidget(string $path, string $slot_name, string $widget_name, ?int $seq = null, array $attributes = [], array $settings = []): array
	{
		$page = CmsPathHelper::resolveWebpage($path);

		if (!is_array($page)) {
			throw new RuntimeException("Webpage not found: {$path}");
		}

		$connection_id = Widget::assignWidgetToWebpage(
			(int) $page['node_id'],
			$slot_name,
			$widget_name,
			$seq,
			true
		);

		if (!is_int($connection_id) || $connection_id <= 0) {
			throw new RuntimeException("Unable to assign widget {$widget_name} to {$path} slot {$slot_name}.");
		}

		self::replaceConnectionAttributes($connection_id, $attributes);
		self::replaceConnectionSettings($connection_id, $widget_name, $settings);

		return self::getWidgetConnectionSnapshot($connection_id);
	}

	/**
	 * @param array<string, scalar|null>|null $attributes
	 * @param array<string, mixed>|null $settings
	 * @return array<string, mixed>
	 */
	public static function updateWidgetConnection(
		int $connection_id,
		?string $slot_name = null,
		?int $seq = null,
		?array $attributes = null,
		?array $settings = null
	): array {
		$connection = Widget::getConnectionData($connection_id);

		if (!is_array($connection)) {
			throw new RuntimeException("Widget connection not found: {$connection_id}");
		}

		$page_id = (int) $connection['page_id'];
		$target_connection_id = $connection_id;
		$target_slot_name = $slot_name !== null && $slot_name !== '' ? $slot_name : (string) $connection['slot_name'];
		$target_seq = $seq;

		if ($target_slot_name !== (string) $connection['slot_name'] || $seq !== null) {
			$existing_attributes = AttributeHandler::getAttributes(
				new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, (string) $connection_id)
			);
			$existing_settings = self::getConnectionSettings($connection_id, (string) $connection['widget_name']);
			$next_attributes = $attributes ?? $existing_attributes;
			$next_settings = $settings ?? $existing_settings;
			$target_seq ??= (int) ($connection['seq'] ?? 0);

			if (!Widget::removeWidgetFromWebpage($connection_id)) {
				throw new RuntimeException("Unable to remove widget connection {$connection_id} before move.");
			}

			$replacement_id = Widget::assignWidgetToWebpage(
				$page_id,
				$target_slot_name,
				(string) $connection['widget_name'],
				$target_seq,
				true
			);

			if (!is_int($replacement_id) || $replacement_id <= 0) {
				throw new RuntimeException("Unable to recreate widget connection {$connection_id}.");
			}

			$target_connection_id = $replacement_id;
			self::replaceConnectionAttributes($target_connection_id, $next_attributes);
			self::replaceConnectionSettings($target_connection_id, (string) $connection['widget_name'], $next_settings);

			return self::getWidgetConnectionSnapshot($target_connection_id) + [
				'replaced_connection_id' => $connection_id,
			];
		}

		if ($attributes !== null) {
			self::replaceConnectionAttributes($target_connection_id, $attributes);
		}

		if ($settings !== null) {
			self::replaceConnectionSettings($target_connection_id, (string) $connection['widget_name'], $settings);
		}

		return self::getWidgetConnectionSnapshot($target_connection_id);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function removeWidget(
		string $path,
		string $slot_name,
		?int $connection_id = null,
		?string $widget_name = null,
		bool $all = false
	): array {
		$targets = self::resolveWidgetRemovalTargets($path, $slot_name, $connection_id, $widget_name, $all);
		$removed = [];

		foreach ($targets as $target_id) {
			if (Widget::removeWidgetFromWebpage((int) $target_id)) {
				$removed[] = ['connection_id' => (int) $target_id];
			}
		}

		return $removed;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function previewRemoveWidget(
		string $path,
		string $slot_name,
		?int $connection_id = null,
		?string $widget_name = null,
		bool $all = false
	): array {
		$targets = self::resolveWidgetRemovalTargets($path, $slot_name, $connection_id, $widget_name, $all);
		$snapshots = array_map(
			static fn (int $target_id): array => self::getWidgetConnectionSnapshot($target_id),
			$targets
		);

		return [
			'status' => 'success',
			'dry_run' => true,
			'path' => $path,
			'slot' => $slot_name,
			'summary' => [
				'touched_pages' => 1,
				'touched_slots' => 1,
				'deleted_widgets' => count($snapshots),
				'emptied_slots' => $snapshots !== [] && count($snapshots) === count(self::getSlotSnapshots((int) $snapshots[0]['page_id'], $slot_name)) ? 1 : 0,
			],
			'targets' => $snapshots,
		];
	}

	/**
	 * @return list<int>
	 */
	private static function resolveWidgetRemovalTargets(
		string $path,
		string $slot_name,
		?int $connection_id,
		?string $widget_name,
		bool $all
	): array {
		$page = CmsPathHelper::resolveWebpage($path);

		if (!is_array($page)) {
			throw new RuntimeException("Webpage not found: {$path}");
		}

		$targets = [];

		if ($connection_id !== null) {
			$connection = Widget::getConnectionData($connection_id);

			if (!is_array($connection)) {
				throw new RuntimeException("Widget connection not found: {$connection_id}");
			}

			if ((int) ($connection['page_id'] ?? 0) !== (int) $page['node_id']) {
				throw new RuntimeException("Widget connection {$connection_id} does not belong to {$path}.");
			}

			if ((string) ($connection['slot_name'] ?? '') !== $slot_name) {
				throw new RuntimeException("Widget connection {$connection_id} does not belong to slot {$slot_name}.");
			}

			$targets[] = $connection_id;
		} else {
			$connections = WidgetConnection::getWidgetsForSlot((int) $page['node_id'], $slot_name);

			foreach ($connections as $connection) {
				if ($widget_name !== null && $connection->getWidgetName() !== $widget_name) {
					continue;
				}

				$targets[] = $connection->getConnectionId();

				if (!$all) {
					break;
				}
			}
		}

		return $targets;
	}

	/**
	 * @param list<CmsWidgetSpec> $spec
	 * @return list<array<string, mixed>>
	 */
	public static function syncWidgetSlot(string $path, string $slot_name, array $spec): array
	{
		$page = CmsPathHelper::resolveWebpage($path);

		if (!is_array($page)) {
			throw new RuntimeException("Webpage not found: {$path}");
		}

		return self::syncSlot((int) $page['node_id'], $slot_name, $spec);
	}

	/**
	 * @param list<CmsWidgetSpec> $spec
	 * @return array<string, mixed>
	 */
	public static function previewWidgetSlotSync(string $path, string $slot_name, array $spec): array
	{
		$page = CmsPathHelper::resolveWebpage($path);

		if (!is_array($page)) {
			throw new RuntimeException("Webpage not found: {$path}");
		}

		$ordered_specs = self::normalizeWidgetSpecs($spec);
		self::assertWidgetSpecsAssignable($ordered_specs);

		return [
			'status' => 'success',
			'dry_run' => true,
			'path' => $path,
			'slot' => $slot_name,
			'summary' => self::buildSlotSyncSummary((int) $page['node_id'], $slot_name, $ordered_specs),
		];
	}

	/**
	 * @param list<CmsWidgetSpec> $spec
	 * @return array<string, mixed>
	 */
	public static function syncWidgetSlotWithSummary(string $path, string $slot_name, array $spec): array
	{
		$page = CmsPathHelper::resolveWebpage($path);

		if (!is_array($page)) {
			throw new RuntimeException("Webpage not found: {$path}");
		}

		$ordered_specs = self::normalizeWidgetSpecs($spec);
		self::assertWidgetSpecsAssignable($ordered_specs);
		$summary = self::buildSlotSyncSummary((int) $page['node_id'], $slot_name, $ordered_specs);
		$connections = self::syncSlot((int) $page['node_id'], $slot_name, $ordered_specs);

		if (class_exists(CmsMutationAuditService::class)) {
			CmsMutationAuditService::recordLeaf('widget.slot_sync', [
				'page_id' => (int) $page['node_id'],
				'resource_path' => $path,
				'slot_name' => $slot_name,
				'affected_count' => ($summary['removed_widgets'] ?? 0) + ($summary['created_widgets'] ?? 0),
				'summary' => $summary,
				'after' => [
					'connections' => $connections,
				],
			]);
		}

		return [
			'status' => 'success',
			'dry_run' => false,
			'path' => $path,
			'slot' => $slot_name,
			'summary' => $summary,
			'connections' => $connections,
		];
	}

	/**
	 * @param list<CmsWidgetSpec> $spec
	 * @return list<CmsWidgetSpec>
	 */
	public static function validateWidgetSlotSpec(string $path, string $slot_name, array $spec): array
	{
		$page = CmsPathHelper::resolveWebpage($path);

		if (!is_array($page)) {
			throw new RuntimeException("Webpage not found: {$path}");
		}

		if (trim($slot_name) === '') {
			throw new InvalidArgumentException('Slot name is required.');
		}

		$ordered_specs = self::normalizeWidgetSpecs($spec);

		self::assertWidgetSpecsAssignable($ordered_specs);

		return $ordered_specs;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function listAcl(string $path, bool $resolved = false): array
	{
		$resource = CmsPathHelper::resolveResource($path);

		if (!is_array($resource)) {
			throw new RuntimeException("Resource not found: {$path}");
		}

		$local_subjects = ResourceAcl::getSubjectsListOfAclObject((int) $resource['node_id']);
		$resolved_subjects = $resolved ? ResourceAcl::getSubjectsListOfAncestorAclObjects((int) $resource['node_id']) : [];

		return [
			'path' => ResourceTreeHandler::getPathFromId((int) $resource['node_id']),
			'resource_id' => (int) $resource['node_id'],
			'inherit' => (bool) ($resource['is_inheriting_acl'] ?? false),
			'local' => array_values($local_subjects),
			'resolved_ancestors' => array_values($resolved_subjects),
		];
	}

	/**
	 * @param CmsAclSpec $spec
	 * @return array<string, mixed>
	 */
	public static function syncAclForPath(string $path, array $spec): array
	{
		$resource = CmsPathHelper::resolveResource($path);

		if (!is_array($resource)) {
			throw new RuntimeException("Resource not found: {$path}");
		}

		self::syncAcl((int) $resource['node_id'], $spec);

		return self::listAcl($path, true);
	}

	/**
	 * @param array<string, list<CmsWidgetSpec>> $slot_specs
	 */
	private static function syncAllSlots(int $page_id, array $slot_specs): void
	{
		$current = WidgetConnection::getWidgetsForPageGroupedBySlot($page_id);

		foreach (array_keys($current) as $slot_name) {
			if (!array_key_exists($slot_name, $slot_specs)) {
				self::syncSlot($page_id, $slot_name, []);
			}
		}

		self::syncSlots($page_id, $slot_specs);
	}

	/**
	 * @param array<string, list<CmsWidgetSpec>> $slot_specs
	 */
	private static function syncSlots(int $page_id, array $slot_specs): void
	{
		foreach ($slot_specs as $slot_name => $widgets) {
			self::syncSlot($page_id, (string) $slot_name, $widgets);
		}
	}

	/**
	 * @param list<CmsWidgetSpec> $widget_specs
	 * @return list<array<string, mixed>>
	 */
	private static function syncSlot(int $page_id, string $slot_name, array $widget_specs): array
	{
		if (trim($slot_name) === '') {
			throw new InvalidArgumentException('Slot name is required.');
		}

		$ordered_specs = self::normalizeWidgetSpecs($widget_specs);
		self::assertWidgetSpecsAssignable($ordered_specs);
		$created = [];
		$pdo = Db::instance();
		$started_transaction = !$pdo->inTransaction();

		try {
			if ($started_transaction) {
				$pdo->beginTransaction();
			}

			$existing_snapshots = self::getSlotSnapshots($page_id, $slot_name);

			foreach ($existing_snapshots as $snapshot) {
				Widget::removeWidgetFromWebpage((int) $snapshot['connection_id']);
			}

			foreach ($ordered_specs as $index => $widget_spec) {
				$widget_name = (string) $widget_spec['widget'];
				$previous_snapshot = is_array($existing_snapshots[$index] ?? null)
					&& (string) ($existing_snapshots[$index]['widget'] ?? '') === $widget_name
					? $existing_snapshots[$index]
					: null;
				$attributes = array_key_exists('attributes', $widget_spec)
					? (array) $widget_spec['attributes']
					: (array) ($previous_snapshot['attributes'] ?? []);
				$settings = array_key_exists('settings', $widget_spec)
					? (array) $widget_spec['settings']
					: (array) ($previous_snapshot['settings'] ?? []);

				$connection_id = Widget::assignWidgetToWebpage(
					$page_id,
					$slot_name,
					$widget_name,
					$index,
					true
				);

				if (!is_int($connection_id) || $connection_id <= 0) {
					throw new RuntimeException("Unable to sync widget {$widget_spec['widget']} on slot {$slot_name}.");
				}

				self::replaceConnectionAttributes($connection_id, $attributes);
				self::replaceConnectionSettings($connection_id, $widget_name, $settings);
				$created[] = self::getWidgetConnectionSnapshot($connection_id);
			}

			if ($started_transaction) {
				$pdo->commit();
			}
		} catch (Throwable $exception) {
			if ($started_transaction && $pdo->inTransaction()) {
				$pdo->rollBack();
			}

			throw $exception;
		}

		return $created;
	}

	/**
	 * @param list<CmsWidgetSpec> $ordered_specs
	 * @return array<string, int>
	 */
	private static function buildSlotSyncSummary(int $page_id, string $slot_name, array $ordered_specs): array
	{
		if (trim($slot_name) === '') {
			throw new InvalidArgumentException('Slot name is required.');
		}

		$existing_snapshots = self::getSlotSnapshots($page_id, $slot_name);
		$preserved_settings = 0;
		$preserved_attributes = 0;

		foreach ($ordered_specs as $index => $widget_spec) {
			$previous_snapshot = is_array($existing_snapshots[$index] ?? null)
				&& (string) ($existing_snapshots[$index]['widget'] ?? '') === (string) $widget_spec['widget']
				? $existing_snapshots[$index]
				: null;

			if ($previous_snapshot === null) {
				continue;
			}

			if (!array_key_exists('settings', $widget_spec) && !empty($previous_snapshot['settings'])) {
				++$preserved_settings;
			}

			if (!array_key_exists('attributes', $widget_spec) && !empty($previous_snapshot['attributes'])) {
				++$preserved_attributes;
			}
		}

		return [
			'touched_pages' => 1,
			'touched_slots' => 1,
			'existing_widgets' => count($existing_snapshots),
			'desired_widgets' => count($ordered_specs),
			'removed_widgets' => count($existing_snapshots),
			'created_widgets' => count($ordered_specs),
			'emptied_slots' => $ordered_specs === [] && $existing_snapshots !== [] ? 1 : 0,
			'preserved_widget_settings' => $preserved_settings,
			'preserved_widget_attributes' => $preserved_attributes,
		];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function getSlotSnapshots(int $page_id, string $slot_name): array
	{
		$snapshots = [];

		foreach (WidgetConnection::getWidgetsForSlot($page_id, $slot_name) as $connection) {
			$snapshots[] = self::getWidgetConnectionSnapshot($connection->getConnectionId());
		}

		return $snapshots;
	}

	/**
	 * @param list<CmsWidgetSpec> $widget_specs
	 * @return list<CmsWidgetSpec>
	 */
	private static function normalizeWidgetSpecs(array $widget_specs): array
	{
		foreach ($widget_specs as $index => $widget_spec) {
			if (!isset($widget_spec['widget']) || trim((string) $widget_spec['widget']) === '') {
				throw new InvalidArgumentException("Widget spec at index {$index} is missing the widget name.");
			}

			$widget_specs[$index]['seq'] = isset($widget_spec['seq'])
				? (int) $widget_spec['seq']
				: $index;
		}

		usort(
			$widget_specs,
			static fn (array $a, array $b): int => ((int) $a['seq']) <=> ((int) $b['seq'])
		);

		return $widget_specs;
	}

	/**
	 * @param list<CmsWidgetSpec> $widget_specs
	 */
	private static function assertWidgetSpecsAssignable(array $widget_specs): void
	{
		foreach ($widget_specs as $widget_spec) {
			$widget_name = (string) $widget_spec['widget'];

			if (!Widget::checkWidgetExists($widget_name)) {
				throw new InvalidArgumentException("Widget does not exist: {$widget_name}");
			}
		}
	}

	/**
	 * @return array<string, list<array<string, mixed>>>
	 */
	private static function listWidgetsBySlot(int $page_id, bool $include_connection_id = true): array
	{
		$slots = [];

		foreach (WidgetConnection::getWidgetsForPageGroupedBySlot($page_id) as $slot_name => $connections) {
			foreach ($connections as $connection) {
				$connection_id = $connection->getConnectionId();
				$connection_data = Widget::getConnectionData($connection_id);

				$widget_spec = [
					'widget' => $connection->getWidgetName(),
					'seq' => (int) ($connection_data['seq'] ?? 0),
					'attributes' => AttributeHandler::getAttributes(
						new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, (string) $connection_id)
					),
					'settings' => self::getConnectionSettings($connection_id, $connection->getWidgetName()),
				];

				if ($include_connection_id) {
					$widget_spec['connection_id'] = $connection_id;
				}

				$slots[$slot_name][] = $widget_spec;
			}
		}

		return $slots;
	}

	/**
	 * @param array<string, scalar|null> $attributes
	 */
	private static function assertWebpageAttributesAllowed(array $attributes): void
	{
		foreach (array_keys($attributes) as $key) {
			$key = (string) $key;

			if (isset(self::RESERVED_WEBPAGE_ATTRIBUTE_KEYS[$key])) {
				throw new InvalidArgumentException("Webpage attribute key '{$key}' is reserved.");
			}
		}
	}

	/**
	 * @param array<string, scalar|null> $attributes
	 */
	private static function replaceConnectionAttributes(int $connection_id, array $attributes): void
	{
		$resource = new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, (string) $connection_id);
		AttributeHandler::deleteAttributes($resource);

		if ($attributes !== []) {
			AttributeHandler::addAttribute($resource, $attributes, true);
		}
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function replaceConnectionSettings(int $connection_id, string $widget_name, array $settings): void
	{
		$resource_name = self::getWidgetSettingsResourceName($widget_name);
		$resource = new AttributeResourceIdentifier($resource_name, (string) $connection_id);
		AttributeHandler::deleteAttributes($resource);

		if ($settings !== []) {
			$settings_handler = self::getWidgetSettingsHandler($widget_name);

			if ($settings_handler !== null) {
				$settings_handler::saveSettings($settings, $connection_id);
			} else {
				WidgetSettings::saveSettings($settings, $connection_id);
			}
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function getWidgetConnectionSnapshot(int $connection_id): array
	{
		$connection = Widget::getConnectionData($connection_id);

		if (!is_array($connection)) {
			throw new RuntimeException("Widget connection not found: {$connection_id}");
		}

		return [
			'connection_id' => $connection_id,
			'page_id' => (int) $connection['page_id'],
			'slot' => (string) $connection['slot_name'],
			'widget' => (string) $connection['widget_name'],
			'seq' => (int) ($connection['seq'] ?? 0),
			'attributes' => AttributeHandler::getAttributes(
				new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, (string) $connection_id)
			),
			'settings' => self::getConnectionSettings($connection_id, (string) $connection['widget_name']),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function getConnectionSettings(int $connection_id, string $widget_name): array
	{
		$settings_handler = self::getWidgetSettingsHandler($widget_name);

		if ($settings_handler !== null) {
			return $settings_handler::getSettings($connection_id);
		}

		return WidgetSettings::getSettings($connection_id);
	}

	private static function getWidgetSettingsResourceName(string $widget_name): string
	{
		$settings_handler = self::getWidgetSettingsHandler($widget_name);

		if ($settings_handler !== null && defined($settings_handler . '::_RESOURCENAME')) {
			/** @var string $resource_name */
			$resource_name = constant($settings_handler . '::_RESOURCENAME');

			return $resource_name;
		}

		return WidgetSettings::_RESOURCENAME;
	}

	private static function getWidgetSettingsHandler(string $widget_name): ?string
	{
		if ($widget_name === '') {
			return null;
		}

		$candidates = [];
		$widget_class_name = 'Widget' . ucwords($widget_name);

		if (class_exists($widget_class_name)) {
			$candidates[] = preg_replace('/^Widget/', '', $widget_class_name) ?: '';
		}

		$candidates[] = $widget_name;

		foreach (array_unique(array_filter($candidates, static fn (string $candidate): bool => $candidate !== '')) as $candidate) {
			if (class_exists($candidate) && method_exists($candidate, 'saveSettings') && method_exists($candidate, 'getSettings')) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * @param CmsAclSpec $spec
	 */
	private static function syncAcl(int $resource_id, array $spec): void
	{
		if (array_key_exists('inherit', $spec)) {
			ResourceAcl::setInheritance($resource_id, (bool) $spec['inherit']);
		}

		if (!array_key_exists('usergroups', $spec) || !is_array($spec['usergroups'])) {
			return;
		}

		$current_rows = array_filter(
			ResourceAcl::getSubjectsListOfAclObject($resource_id),
			static fn (array $row): bool => ($row['subject_type'] ?? null) === 'usergroup'
		);
		$current_by_group_id = [];

		foreach ($current_rows as $row) {
			$current_by_group_id[(int) $row['subject_id']] = $row;
		}

		$desired_group_ids = [];

		foreach ($spec['usergroups'] as $usergroup_title => $permissions) {
			$usergroup_id = self::resolveAclUsergroupId((string) $usergroup_title);

			if ($usergroup_id <= 0) {
				throw new RuntimeException("Usergroup not found for ACL sync: {$usergroup_title}");
			}

			$desired_group_ids[] = $usergroup_id;

			if (!isset($current_by_group_id[$usergroup_id])) {
				if (!ResourceAcl::assignToUsergroup($usergroup_id, $resource_id)) {
					throw new RuntimeException("Unable to assign ACL subject {$usergroup_title} to resource {$resource_id}");
				}

				$current_by_group_id[$usergroup_id] = DbHelper::selectOne('resource_acl', [
					'resource_id' => $resource_id,
					'subject_type' => 'usergroup',
					'subject_id' => $usergroup_id,
				]);
			}

			$acl_id = (int) ($current_by_group_id[$usergroup_id]['acl_id'] ?? 0);

			if ($acl_id <= 0) {
				throw new RuntimeException("Unable to load ACL row for {$usergroup_title}.");
			}

			$save_data = ['acl_id' => $acl_id];

			foreach (self::ACL_PERMISSION_TO_COLUMN as $permission => $column) {
				$save_data[$column] = !empty($permissions[$permission]) ? 1 : 0;
			}

			ResourceAcl::updateAcl($acl_id, $save_data);
		}

		foreach ($current_by_group_id as $usergroup_id => $row) {
			if (in_array((int) $usergroup_id, $desired_group_ids, true)) {
				continue;
			}

			ResourceAcl::deleteAcl((int) $row['acl_id']);
		}
	}

	private static function resolveAclUsergroupId(string $usergroup_title): int
	{
		return match ($usergroup_title) {
			'Everyone' => Usergroups::SYSTEMUSERGROUP_EVERYBODY,
			'Logged in users' => Usergroups::SYSTEMUSERGROUP_LOGGEDIN,
			default => (int) DbHelper::selectOneColumn('usergroups_tree', ['title' => $usergroup_title], '', 'node_id'),
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function exportAclSpec(int $resource_id): array
	{
		$resource = ResourceTreeHandler::getResourceTreeEntryDataById($resource_id);
		$local = ResourceAcl::getSubjectsListOfAclObject($resource_id);
		$usergroups = [];

		foreach ($local as $row) {
			if (($row['subject_type'] ?? null) !== 'usergroup') {
				continue;
			}

			$title = (string) DbHelper::selectOneColumn('usergroups_tree', ['node_id' => (int) $row['subject_id']], '', 'title');

			if ($title === '') {
				continue;
			}

			$usergroups[$title] = [
				'view' => (bool) ($row['allow_view'] ?? false),
				'list' => (bool) ($row['allow_list'] ?? false),
				'create' => (bool) ($row['allow_create'] ?? false),
				'edit' => (bool) ($row['allow_edit'] ?? false),
				'delete' => (bool) ($row['allow_delete'] ?? false),
				'publish' => (bool) ($row['allow_publish'] ?? false),
			];
		}

		ksort($usergroups);

		return [
			'inherit' => (bool) ($resource['is_inheriting_acl'] ?? false),
			'usergroups' => $usergroups,
		];
	}
}
