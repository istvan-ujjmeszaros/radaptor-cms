<?php

declare(strict_types=1);

class EventResourceImportFile extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		$target_folder = trim((string) Request::_POST('target_folder', ''));
		$resource_name = trim((string) Request::_POST('resource_name', ''));

		if ($target_folder === '') {
			return PolicyDecision::deny('target_folder is required');
		}

		if ($resource_name === '') {
			return PolicyDecision::deny('resource_name is required');
		}

		$folder_path = CmsPathHelper::splitFolderPath($target_folder)['normalized_path'];
		$existing = ResourceTreeHandler::getResourceTreeEntryData($folder_path, $resource_name, Config::APP_DOMAIN_CONTEXT->value());

		if (is_array($existing)) {
			$logged_in = McpCmsAuthoringAuthorization::loggedIn($policyContext);

			if (!$logged_in->allow) {
				return $logged_in;
			}

			if (($existing['node_type'] ?? null) !== 'file') {
				return PolicyDecision::deny("resource path exists and is not a file: {$folder_path}{$resource_name}");
			}

			return ResourceAcl::canAccessResource((int) $existing['node_id'], ResourceAcl::_ACL_EDIT)
				? PolicyDecision::allow()
				: PolicyDecision::deny("resource edit denied: {$folder_path}{$resource_name}");
		}

		$folder = CmsPathHelper::resolveFolder($target_folder);

		if (is_array($folder)) {
			$logged_in = McpCmsAuthoringAuthorization::loggedIn($policyContext);

			if (!$logged_in->allow) {
				return $logged_in;
			}

			return ResourceAcl::canAccessResource((int) $folder['node_id'], ResourceAcl::_ACL_CREATE)
				? PolicyDecision::allow()
				: PolicyDecision::deny("resource create denied: {$target_folder}");
		}

		return McpCmsAuthoringAuthorization::createFolder($target_folder, $policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'resource.import_file',
			'group' => 'CMS Authoring',
			'name' => 'Import file into resource tree',
			'summary' => 'Imports a readable migration-source file into a CMS resource folder.',
			'description' => 'Copies a file from an explicitly mounted migration source into the media container and creates or replaces a file resource in the resource tree.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('source_path', 'body', 'string', true, 'Absolute file path under APP_MIGRATION_SOURCE_ROOTS.'),
					BrowserEventDocumentationHelper::param('target_folder', 'body', 'string', true, 'Target resource folder path.'),
					BrowserEventDocumentationHelper::param('resource_name', 'body', 'string', true, 'Resource filename to create or replace.'),
					BrowserEventDocumentationHelper::param('title', 'body', 'string', false, 'Optional file title.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns resource id, file id, path, mime type, and replacement flag.',
			],
			'authorization' => [
				'visibility' => 'resource ACL',
				'description' => 'Requires create permission for new files and edit permission when replacing an existing file.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.resource.import_file',
				'risk' => 'write',
			],
			'notes' => [],
			'side_effects' => BrowserEventDocumentationHelper::lines('Creates a media-container file row and creates or updates a resource_tree file entry.'),
		];
	}

	public function run(): void
	{
		$source_path = trim((string) Request::_POST('source_path', ''));
		$target_folder = trim((string) Request::_POST('target_folder', ''));
		$resource_name = trim((string) Request::_POST('resource_name', ''));
		$title = trim((string) Request::_POST('title', ''));

		if ($source_path === '') {
			ApiResponse::renderError('MISSING_SOURCE_PATH', 'source_path is required.', 400);

			return;
		}

		if ($target_folder === '') {
			ApiResponse::renderError('MISSING_TARGET_FOLDER', 'target_folder is required.', 400);

			return;
		}

		if ($resource_name === '') {
			ApiResponse::renderError('MISSING_RESOURCE_NAME', 'resource_name is required.', 400);

			return;
		}

		try {
			ApiResponse::renderSuccess(CmsResourceImportService::importFile($source_path, $target_folder, $resource_name, $title));
		} catch (Throwable $exception) {
			ApiResponse::renderError('RESOURCE_IMPORT_FILE_FAILED', $exception->getMessage(), 400);
		}
	}
}
