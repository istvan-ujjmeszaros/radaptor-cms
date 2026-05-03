<?php

declare(strict_types=1);

class CmsUsageInspector
{
	/**
	 * @return array<string, mixed>
	 */
	public static function inspectLayoutUsage(?string $layout_id = null): array
	{
		$layout_id = is_string($layout_id) ? trim($layout_id) : '';
		$pages = self::findLayoutPages($layout_id !== '' ? $layout_id : null);
		$counts = [];

		foreach ($pages as $page) {
			$layout = (string) ($page['layout'] ?? '');

			if ($layout === '') {
				$layout = '(none)';
			}

			$counts[$layout] = ($counts[$layout] ?? 0) + 1;
		}

		ksort($counts);

		$layout_counts = [];

		foreach ($counts as $layout => $count) {
			$layout_counts[] = [
				'layout' => $layout,
				'count' => $count,
			];
		}

		return [
			'layout' => $layout_id !== '' ? $layout_id : null,
			'in_use' => count($pages) > 0,
			'count' => count($pages),
			'pages' => $pages,
			'layout_counts' => $layout_counts,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function inspectFileUsage(?int $file_id = null, ?string $path = null): array
	{
		$target = self::resolveFileTarget($file_id, $path);
		$resolved_file_id = (int) $target['file_id'];
		$file_data = FileContainer::getDataFromFileId($resolved_file_id);
		$references = self::findFileResourceReferences($resolved_file_id);
		$physical_exists = false;

		if (is_array($file_data)) {
			$physical_exists = is_file(FileContainer::realPathFromFileId($resolved_file_id));
		}

		return [
			'file_id' => $resolved_file_id,
			'input_path' => $path !== null && trim($path) !== '' ? CmsPathHelper::normalizePath($path) : null,
			'resolved_from_path' => $target['resource'] ?? null,
			'file_exists' => is_array($file_data),
			'physical_exists' => $physical_exists,
			'file' => is_array($file_data) ? [
				'file_id' => (int) $file_data['file_id'],
				'md5_hash' => (string) $file_data['md5_hash'],
				'storage_folder_id' => (int) $file_data['storage_folder_id'],
				'filesize' => (int) $file_data['filesize'],
			] : null,
			'referenced_by_vfs' => count($references) > 0,
			'reference_count' => count($references),
			'resources' => $references,
		];
	}

	/**
	 * @return list<array{page_id: int, path: string, layout: string|null, title: string|null}>
	 */
	private static function findLayoutPages(?string $layout_id): array
	{
		$params = [ResourceNames::RESOURCE_DATA];
		// Webpage layouts are persisted as resource_data attributes by the CMS page tooling.
		$where = "a.resource_name = ? AND a.param_name = 'layout' AND rt.node_type = 'webpage'";

		if ($layout_id !== null) {
			$where .= ' AND a.param_value = ?';
			$params[] = $layout_id;
		}

		$stmt = DbHelper::prexecute(
			"SELECT rt.node_id, a.param_value AS layout
			 FROM attributes a
			 INNER JOIN resource_tree rt ON rt.node_id = a.resource_id
			 WHERE {$where}
			 ORDER BY rt.path, rt.resource_name",
			$params
		);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$pages = [];

		foreach ($rows as $row) {
			$page_id = (int) $row['node_id'];
			$attributes = ResourceTypeWebpage::getExtradata($page_id);
			$pages[] = [
				'page_id' => $page_id,
				'path' => Url::getSeoUrl($page_id, false) ?? ResourceTreeHandler::getPathFromId($page_id),
				'layout' => (string) ($row['layout'] ?? ''),
				'title' => isset($attributes['title']) && (string) $attributes['title'] !== ''
					? (string) $attributes['title']
					: null,
			];
		}

		return $pages;
	}

	/**
	 * @return array{file_id: int, resource?: array<string, mixed>}
	 */
	private static function resolveFileTarget(?int $file_id, ?string $path): array
	{
		$path = is_string($path) ? trim($path) : '';

		if ($path !== '') {
			$resource = CmsPathHelper::resolveResource($path);

			if (!is_array($resource)) {
				throw new RuntimeException("Resource not found: {$path}");
			}

			if (($resource['node_type'] ?? '') !== 'file') {
				throw new RuntimeException("Resource is not a file: {$path}");
			}

			$attributes = AttributeHandler::getAttributes(
				new AttributeResourceIdentifier(ResourceNames::RESOURCE_DATA, (string) $resource['node_id'])
			);
			$resolved_file_id = is_numeric($attributes['file_id'] ?? null) ? (int) $attributes['file_id'] : 0;

			if ($resolved_file_id <= 0) {
				throw new RuntimeException("File resource has no file_id: {$path}");
			}

			return [
				'file_id' => $resolved_file_id,
				'resource' => self::resourceReferenceFromRow($resource),
			];
		}

		if ($file_id === null || $file_id <= 0) {
			throw new InvalidArgumentException('file_id or path is required.');
		}

		return ['file_id' => $file_id];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function findFileResourceReferences(int $file_id): array
	{
		$stmt = DbHelper::prexecute(
			"SELECT rt.*
			 FROM attributes a
			 INNER JOIN resource_tree rt ON rt.node_id = a.resource_id
			 WHERE a.resource_name = ?
			   AND a.param_name = 'file_id'
			   AND a.param_value = ?
			 ORDER BY rt.path, rt.resource_name",
			[
				ResourceNames::RESOURCE_DATA,
				(string) $file_id,
			]
		);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$references = [];

		foreach ($rows as $row) {
			$references[] = self::resourceReferenceFromRow($row);
		}

		return $references;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private static function resourceReferenceFromRow(array $row): array
	{
		$node_id = (int) $row['node_id'];
		$attributes = AttributeHandler::getAttributes(
			new AttributeResourceIdentifier(ResourceNames::RESOURCE_DATA, (string) $node_id)
		);

		return [
			'node_id' => $node_id,
			'path' => ResourceTreeHandler::getPathFromId($node_id),
			'node_type' => (string) ($row['node_type'] ?? ''),
			'resource_name' => (string) ($row['resource_name'] ?? ''),
			'title' => isset($attributes['title']) && (string) $attributes['title'] !== ''
				? (string) $attributes['title']
				: null,
			'mime' => isset($attributes['mime']) && (string) $attributes['mime'] !== ''
				? (string) $attributes['mime']
				: null,
		];
	}
}
