<?php

declare(strict_types=1);

class EventResourceList extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		$path = trim((string) Request::_GET('path', '/'));

		if ($path === '') {
			$path = '/';
		}

		return McpCmsAuthoringAuthorization::resourcePath($path, ResourceAcl::_ACL_LIST, $policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'resource.list',
			'group' => 'CMS Authoring',
			'name' => 'List resource tree entries',
			'summary' => 'Lists direct or recursive CMS resource children below a path.',
			'description' => 'Returns resource tree entries visible to the current user. Recursive mode walks folder descendants through ResourceTreeHandler, preserving resource ACL checks.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('path', 'query', 'string', false, 'Resource path, defaults to /.'),
					BrowserEventDocumentationHelper::param('recursive', 'query', 'bool', false, 'Whether to include descendants recursively.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns visible resource entries.',
			],
			'authorization' => [
				'visibility' => 'resource ACL',
				'description' => 'Requires list permission on the requested resource.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.resource.list',
				'risk' => 'read',
			],
			'notes' => [],
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		$path = trim((string) Request::_GET('path', '/'));
		$recursive = filter_var(Request::_GET('recursive', false), FILTER_VALIDATE_BOOLEAN);

		if ($path === '') {
			$path = '/';
		}

		try {
			$resource = CmsPathHelper::resolveFolder($path) ?? CmsPathHelper::resolveResource($path);

			if (!is_array($resource)) {
				throw new RuntimeException("Resource not found: {$path}");
			}

			$entries = $recursive
				? $this->listRecursive((int) $resource['node_id'])
				: $this->normalizeEntries(CmsResourceSpecService::listResources($path));

			ApiResponse::renderSuccess([
				'path' => $path,
				'recursive' => $recursive,
				'count' => count($entries),
				'resources' => $entries,
			]);
		} catch (Throwable $exception) {
			ApiResponse::renderError('RESOURCE_LIST_FAILED', $exception->getMessage(), 400);
		}
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function listRecursive(int $parent_id): array
	{
		$return = [];

		foreach ($this->normalizeEntries(ResourceTreeHandler::getResourceChildrenDetailed($parent_id)) as $entry) {
			$return[] = $entry;

			if (($entry['node_type'] ?? '') === 'folder') {
				$return = array_merge($return, $this->listRecursive((int) $entry['node_id']));
			}
		}

		return $return;
	}

	/**
	 * @param list<array<string, mixed>> $entries
	 * @return list<array<string, mixed>>
	 */
	private function normalizeEntries(array $entries): array
	{
		foreach ($entries as $index => $entry) {
			$entries[$index]['full_path'] = ResourceTreeHandler::getPathFromId((int) $entry['node_id']);
		}

		return $entries;
	}
}
