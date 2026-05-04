<?php

declare(strict_types=1);

final class CmsResourceTreeSpecService
{
	private const int SPEC_VERSION = 1;
	private const string MANAGED_BY = 'repo_spec';
	private const string ATTR_MANAGED_BY = '_repo_spec_managed_by';
	private const string ATTR_SPEC_ID = '_repo_spec_id';
	private const string ATTR_LAST_HASH = '_repo_spec_last_applied_hash';

	/**
	 * @return array<string, mixed>
	 */
	public static function exportTreeSpec(string $path = '/'): array
	{
		return [
			'version' => self::SPEC_VERSION,
			'root' => CmsPathHelper::normalizePath($path),
			'resources' => self::exportResourceBranch($path),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function loadSpecFile(string $path): array
	{
		$resolved_path = self::resolveSpecPath($path);
		$spec = require $resolved_path;

		if (!is_array($spec)) {
			throw new InvalidArgumentException("Resource spec file must return an array: {$resolved_path}");
		}

		return $spec;
	}

	/**
	 * @param array<string, mixed> $spec
	 * @return array<string, mixed>
	 */
	public static function diffSpec(array $spec): array
	{
		$resources = self::flattenResources($spec);
		$results = [];
		$desired_paths = [];
		$summary = [
			'create' => 0,
			'update' => 0,
			'unchanged' => 0,
			'conflict' => 0,
			'extra' => 0,
		];

		foreach ($resources as $resource) {
			$resource = self::normalizeResourceSpec($resource);
			$path = (string) $resource['path'];
			$type = (string) $resource['type'];
			$desired_paths[$path] = true;
			$current = CmsPathHelper::resolveResource($path);

			if (!is_array($current)) {
				$results[] = [
					'path' => $path,
					'type' => $type,
					'action' => 'create',
					'status' => 'pending',
					'message' => 'Resource is missing.',
				];
				++$summary['create'];

				continue;
			}

			if (!self::resourceTypeMatches($type, (string) ($current['node_type'] ?? ''))) {
				$results[] = [
					'path' => $path,
					'type' => $type,
					'action' => 'conflict',
					'status' => 'blocked',
					'message' => "Existing resource type is {$current['node_type']}.",
				];
				++$summary['conflict'];

				continue;
			}

			$desired_hash = self::hashResourceSpec($resource);
			$current_hash = self::hashCurrentResourceForDesired((int) $current['node_id'], $path, $resource);
			$tracking = self::getTracking((int) $current['node_id']);

			if (
				($tracking['managed_by'] ?? null) === self::MANAGED_BY
				&& ($tracking['last_hash'] ?? '') !== ''
				&& hash_equals((string) $tracking['last_hash'], $desired_hash)
				&& !hash_equals($desired_hash, $current_hash)
			) {
				$results[] = [
					'path' => $path,
					'type' => $type,
					'action' => 'conflict',
					'status' => 'blocked',
					'message' => 'Managed resource changed since the last repo-spec sync.',
				];
				++$summary['conflict'];

				continue;
			}

			$action = hash_equals($desired_hash, $current_hash) ? 'unchanged' : 'update';
			$results[] = [
				'path' => $path,
				'type' => $type,
				'action' => $action,
				'status' => $action === 'unchanged' ? 'ok' : 'pending',
				'message' => $action === 'unchanged' ? 'Resource matches spec.' : 'Resource differs from spec.',
			];
			++$summary[$action];
		}

		foreach (self::listCurrentResourcePaths((string) ($spec['root'] ?? '/')) as $path => $type) {
			if (isset($desired_paths[$path])) {
				continue;
			}

			$results[] = [
				'path' => $path,
				'type' => $type,
				'action' => 'extra',
				'status' => 'warning',
				'message' => 'Resource exists in DB but is not declared in the spec.',
			];
			++$summary['extra'];
		}

		return [
			'status' => $summary['conflict'] > 0 ? 'conflict' : 'success',
			'summary' => $summary,
			'resources' => $results,
		];
	}

	/**
	 * @param array<string, mixed> $spec
	 * @return array<string, mixed>
	 */
	public static function syncSpec(array $spec, bool $dry_run = true): array
	{
		$diff = self::diffSpec($spec);

		if ($dry_run) {
			$diff['dry_run'] = true;

			return $diff;
		}

		if (($diff['summary']['conflict'] ?? 0) > 0) {
			throw new RuntimeException('Resource spec has conflicts; run resource-spec:diff and resolve them before applying.');
		}

		$applied = [];

		foreach (self::flattenResources($spec) as $resource) {
			$resource = self::normalizeResourceSpec($resource);
			$path = (string) $resource['path'];
			$type = (string) $resource['type'];

			/** @var int $resource_id */
			$resource_id = ResourceTreeHandler::withProtectedResourceMutationBypass(
				static function () use ($resource, $type): int {
					if ($type === 'folder') {
						return CmsResourceSpecService::upsertFolder($resource);
					}

					return CmsResourceSpecService::upsertWebpage($resource);
				}
			);

			self::markManaged($resource_id, self::hashResourceSpec($resource), self::buildSpecId($resource));
			$applied[] = [
				'path' => $path,
				'type' => $type,
				'resource_id' => $resource_id,
			];
		}

		return [
			'status' => 'success',
			'dry_run' => false,
			'applied' => $applied,
			'after' => self::diffSpec($spec),
		];
	}

	/**
	 * @param array<string, mixed> $spec
	 * @return list<array<string, mixed>>
	 */
	public static function flattenResources(array $spec): array
	{
		if (isset($spec['type'])) {
			return self::flattenResourceList([$spec]);
		}

		if (!isset($spec['resources']) || !is_array($spec['resources'])) {
			throw new InvalidArgumentException('Resource spec must contain a resources array.');
		}

		return self::flattenResourceList($spec['resources']);
	}

	/**
	 * @param array<int, mixed> $resources
	 * @return list<array<string, mixed>>
	 */
	private static function flattenResourceList(array $resources): array
	{
		$flat = [];

		foreach ($resources as $resource) {
			if (!is_array($resource)) {
				throw new InvalidArgumentException('Each resource spec entry must be an array.');
			}

			$children = $resource['children'] ?? [];
			unset($resource['children']);
			$flat[] = $resource;

			if (is_array($children) && $children !== []) {
				$flat = [...$flat, ...self::flattenResourceList($children)];
			}
		}

		return $flat;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function exportResourceBranch(string $path): array
	{
		$resource = CmsPathHelper::resolveResource($path);

		if (!is_array($resource)) {
			throw new RuntimeException("Resource not found: {$path}");
		}

		$spec = CmsResourceSpecService::exportResourceSpec($path);
		$node_type = (string) ($resource['node_type'] ?? '');

		if ($node_type === 'folder' || $node_type === 'root') {
			$children = [];

			foreach (ResourceTreeHandler::getResourceChildrenDetailedUnfiltered((int) $resource['node_id']) as $child) {
				$child_path = ResourceTreeHandler::getPathFromId((int) $child['node_id']);
				$children = [...$children, ...self::exportResourceBranch($child_path)];
			}

			if ($children !== []) {
				$spec['children'] = $children;
			}
		}

		return [$spec];
	}

	/**
	 * @param array<string, mixed> $resource
	 * @return array<string, mixed>
	 */
	private static function normalizeResourceSpec(array $resource): array
	{
		if (!isset($resource['type']) || !is_string($resource['type']) || trim($resource['type']) === '') {
			throw new InvalidArgumentException('Resource spec entry is missing type.');
		}

		if (!isset($resource['path']) || !is_string($resource['path']) || trim($resource['path']) === '') {
			throw new InvalidArgumentException('Resource spec entry is missing path.');
		}

		$type = (string) $resource['type'];

		if (!in_array($type, ['folder', 'webpage'], true)) {
			throw new InvalidArgumentException("Unsupported resource spec type: {$type}");
		}

		$resource['type'] = $type;
		$resource['path'] = $type === 'folder'
			? CmsPathHelper::normalizePath((string) $resource['path'])
			: CmsPathHelper::splitWebpagePath((string) $resource['path'])['normalized_path'];

		return $resource;
	}

	private static function resourceTypeMatches(string $expected, string $actual): bool
	{
		return $expected === 'folder'
			? in_array($actual, ['folder', 'root'], true)
			: $actual === 'webpage';
	}

	/**
	 * Hash only the current fields that the desired resource spec explicitly
	 * manages. This lets small specs own just a page title or one slot without
	 * treating unrelated admin edits as conflicts.
	 *
	 * @param array<string, mixed> $desired
	 */
	private static function hashCurrentResourceForDesired(int $resource_id, string $path, array $desired): string
	{
		$current = self::exportCurrentResourceForHash($resource_id, $path);
		$projected = [
			'type' => (string) $desired['type'],
			'path' => (string) $desired['path'],
		];

		foreach (['layout', 'catcher'] as $key) {
			if (array_key_exists($key, $desired)) {
				$projected[$key] = $current[$key] ?? null;
			}
		}

		if (array_key_exists('attributes', $desired) && is_array($desired['attributes'])) {
			$projected['attributes'] = [];

			foreach (array_keys($desired['attributes']) as $key) {
				$projected['attributes'][(string) $key] = $current['attributes'][$key] ?? null;
			}
		}

		if (array_key_exists('acl', $desired)) {
			$projected['acl'] = $current['acl'] ?? null;
		}

		if (array_key_exists('slots', $desired) && is_array($desired['slots'])) {
			$projected['slots'] = [];

			foreach ($desired['slots'] as $slot_name => $desired_widgets) {
				$slot_name = (string) $slot_name;
				$current_widgets = is_array($current['slots'][$slot_name] ?? null) ? $current['slots'][$slot_name] : [];
				$projected['slots'][$slot_name] = [];

				foreach (array_values((array) $desired_widgets) as $index => $desired_widget) {
					if (!is_array($desired_widget)) {
						continue;
					}

					$current_widget = is_array($current_widgets[$index] ?? null) ? $current_widgets[$index] : [];
					$projected_widget = [
						'widget' => $current_widget['widget'] ?? null,
					];

					if (array_key_exists('seq', $desired_widget)) {
						$projected_widget['seq'] = $current_widget['seq'] ?? null;
					}

					if (array_key_exists('attributes', $desired_widget) && is_array($desired_widget['attributes'])) {
						$projected_widget['attributes'] = [];

						foreach (array_keys($desired_widget['attributes']) as $key) {
							$projected_widget['attributes'][(string) $key] = $current_widget['attributes'][$key] ?? null;
						}
					}

					if (array_key_exists('settings', $desired_widget) && is_array($desired_widget['settings'])) {
						$projected_widget['settings'] = [];

						foreach (array_keys($desired_widget['settings']) as $key) {
							$projected_widget['settings'][(string) $key] = $current_widget['settings'][$key] ?? null;
						}
					}

					$projected['slots'][$slot_name][] = $projected_widget;
				}
			}
		}

		return self::hashResourceSpec($projected);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function exportCurrentResourceForHash(int $resource_id, string $path): array
	{
		$resource = ResourceTreeHandler::getResourceTreeEntryDataById($resource_id);

		if (!is_array($resource)) {
			throw new RuntimeException("Resource not found: {$resource_id}");
		}

		return ($resource['node_type'] ?? '') === 'webpage'
			? CmsResourceSpecService::exportWebpageSpec($path)
			: CmsResourceSpecService::exportFolderSpec($path);
	}

	/**
	 * @param array<string, mixed> $resource
	 */
	private static function hashResourceSpec(array $resource): string
	{
		$resource = self::normalizeResourceSpec($resource);
		unset($resource['children']);

		if (($resource['type'] ?? null) === 'webpage') {
			$path_parts = CmsPathHelper::splitWebpagePath((string) $resource['path']);

			if ($path_parts['resource_name'] === 'index.html') {
				$resource['path'] = $path_parts['folder'];
			}
		}

		self::ksortRecursive($resource);

		return hash('sha256', json_encode($resource, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
	}

	/**
	 * @param array<string, mixed> $resource
	 */
	private static function buildSpecId(array $resource): string
	{
		return (string) $resource['type'] . ':' . (string) $resource['path'];
	}

	/**
	 * @return array{managed_by?: string, spec_id?: string, last_hash?: string}
	 */
	private static function getTracking(int $resource_id): array
	{
		$attributes = AttributeHandler::getAttributes(
			new AttributeResourceIdentifier(ResourceNames::RESOURCE_DATA, (string) $resource_id)
		);

		return [
			'managed_by' => isset($attributes[self::ATTR_MANAGED_BY]) ? (string) $attributes[self::ATTR_MANAGED_BY] : null,
			'spec_id' => isset($attributes[self::ATTR_SPEC_ID]) ? (string) $attributes[self::ATTR_SPEC_ID] : null,
			'last_hash' => isset($attributes[self::ATTR_LAST_HASH]) ? (string) $attributes[self::ATTR_LAST_HASH] : null,
		];
	}

	private static function markManaged(int $resource_id, string $hash, string $spec_id): void
	{
		AttributeHandler::addAttribute(
			new AttributeResourceIdentifier(ResourceNames::RESOURCE_DATA, (string) $resource_id),
			[
				self::ATTR_MANAGED_BY => self::MANAGED_BY,
				self::ATTR_SPEC_ID => $spec_id,
				self::ATTR_LAST_HASH => $hash,
			]
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function listCurrentResourcePaths(string $root_path): array
	{
		$root = CmsPathHelper::resolveFolder($root_path) ?? CmsPathHelper::resolveResource($root_path);

		if (!is_array($root)) {
			return [];
		}

		$paths = [];
		self::collectResourcePaths((int) $root['node_id'], $paths);

		return $paths;
	}

	/**
	 * @param array<string, string> $paths
	 */
	private static function collectResourcePaths(int $parent_id, array &$paths): void
	{
		foreach (ResourceTreeHandler::getResourceChildrenDetailedUnfiltered($parent_id) as $child) {
			$path = ResourceTreeHandler::getPathFromId((int) $child['node_id']);
			$type = (string) ($child['node_type'] ?? '');
			$paths[$path] = $type === 'webpage' ? 'webpage' : 'folder';

			if ($type === 'folder' || $type === 'root') {
				self::collectResourcePaths((int) $child['node_id'], $paths);
			}
		}
	}

	private static function resolveSpecPath(string $path): string
	{
		$path = trim($path);

		if ($path === '') {
			throw new InvalidArgumentException('Spec path is required.');
		}

		$candidates = str_starts_with($path, '/')
			? [$path]
			: [DEPLOY_ROOT . $path, getcwd() . '/' . $path];

		foreach ($candidates as $candidate) {
			if (is_file($candidate) && is_readable($candidate)) {
				return $candidate;
			}
		}

		throw new RuntimeException("Resource spec file not found: {$path}");
	}

	/**
	 * @param array<string, mixed> $value
	 */
	private static function ksortRecursive(array &$value): void
	{
		ksort($value);

		foreach ($value as &$child) {
			if (is_array($child)) {
				self::ksortRecursive($child);
			}
		}
	}
}
