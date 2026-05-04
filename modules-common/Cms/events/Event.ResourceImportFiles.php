<?php

declare(strict_types=1);

class EventResourceImportFiles extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		$target_folder = trim((string) Request::_POST('target_folder', ''));
		$files = Request::_POST('files', null);

		if ($target_folder === '') {
			return PolicyDecision::deny('target_folder is required');
		}

		if (!is_array($files) || $files === []) {
			return PolicyDecision::deny('files must be a non-empty array');
		}

		$logged_in = McpCmsAuthoringAuthorization::loggedIn($policyContext);

		if (!$logged_in->allow) {
			return $logged_in;
		}

		$folder_path = CmsPathHelper::splitFolderPath($target_folder)['normalized_path'];
		$folder = CmsPathHelper::resolveFolder($target_folder);

		if (!is_array($folder)) {
			return McpCmsAuthoringAuthorization::createFolder($target_folder, $policyContext);
		}

		foreach (array_values($files) as $index => $file) {
			if (!is_array($file)) {
				return PolicyDecision::deny("file spec at index {$index} must be an object");
			}

			$resource_name = trim((string) ($file['resource_name'] ?? ''));

			if ($resource_name === '') {
				return PolicyDecision::deny("file spec at index {$index} is missing resource_name");
			}

			$existing = ResourceTreeHandler::getResourceTreeEntryData($folder_path, $resource_name);

			if (is_array($existing)) {
				if (($existing['node_type'] ?? null) !== 'file') {
					return PolicyDecision::deny("resource path exists and is not a file: {$folder_path}{$resource_name}");
				}

				if (!ResourceAcl::canAccessResource((int) $existing['node_id'], ResourceAcl::_ACL_EDIT)) {
					return PolicyDecision::deny("resource edit denied: {$folder_path}{$resource_name}");
				}
			} elseif (!ResourceAcl::canAccessResource((int) $folder['node_id'], ResourceAcl::_ACL_CREATE)) {
				return PolicyDecision::deny("resource create denied: {$target_folder}");
			}
		}

		return PolicyDecision::allow();
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'resource.import_files',
			'group' => 'CMS Authoring',
			'name' => 'Import files into resource tree',
			'summary' => 'Imports multiple readable migration-source files into one CMS resource folder.',
			'description' => 'Batch wrapper around resource.import_file. Each file is copied from an explicitly mounted migration source into the media container and created or replaced in the target resource folder.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('target_folder', 'body', 'string', true, 'Target resource folder path.'),
					BrowserEventDocumentationHelper::param('files', 'body', 'json-array', true, 'List of file specs with source_path, resource_name, and optional title.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns one result entry per imported file.',
			],
			'authorization' => [
				'visibility' => 'resource ACL',
				'description' => 'Requires create permission for new files and edit permission when replacing existing files.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.resource.import_files',
				'risk' => 'write',
			],
			'notes' => [],
			'side_effects' => BrowserEventDocumentationHelper::lines('Creates media-container file rows and creates or updates resource_tree file entries.'),
		];
	}

	public function run(): void
	{
		$target_folder = trim((string) Request::_POST('target_folder', ''));
		$files = Request::_POST('files', null);

		if ($target_folder === '') {
			ApiResponse::renderError('MISSING_TARGET_FOLDER', 'target_folder is required.', 400);

			return;
		}

		if (!is_array($files) || $files === []) {
			ApiResponse::renderError('INVALID_FILES', 'files must be a non-empty array.', 400);

			return;
		}

		$results = [];

		try {
			foreach (array_values($files) as $index => $file) {
				if (!is_array($file)) {
					throw new InvalidArgumentException("File spec at index {$index} must be an object.");
				}

				$source_path = trim((string) ($file['source_path'] ?? ''));
				$resource_name = trim((string) ($file['resource_name'] ?? ''));
				$title = trim((string) ($file['title'] ?? ''));

				if ($source_path === '') {
					throw new InvalidArgumentException("File spec at index {$index} is missing source_path.");
				}

				if ($resource_name === '') {
					throw new InvalidArgumentException("File spec at index {$index} is missing resource_name.");
				}

				$results[] = CmsResourceImportService::importFile($source_path, $target_folder, $resource_name, $title);
			}

			ApiResponse::renderSuccess([
				'target_folder' => $target_folder,
				'imported_count' => count($results),
				'files' => $results,
			]);
		} catch (Throwable $exception) {
			ApiResponse::renderError('RESOURCE_IMPORT_FILES_FAILED', $exception->getMessage(), 400);
		}
	}
}
