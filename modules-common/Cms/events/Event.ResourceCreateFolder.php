<?php

declare(strict_types=1);

class EventResourceCreateFolder extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return McpCmsAuthoringAuthorization::createFolder((string) Request::_POST('path', ''), $policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'resource.create_folder',
			'group' => 'CMS Authoring',
			'name' => 'Create resource folder',
			'summary' => 'Creates or ensures a resource folder.',
			'description' => 'Creates a folder path in the CMS resource tree if it does not already exist.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('path', 'body', 'string', true, 'Folder path.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns folder id and resource spec.',
			],
			'authorization' => [
				'visibility' => 'resource ACL',
				'description' => 'Requires create permission on the parent folder.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.resource.create_folder',
				'risk' => 'write',
			],
			'notes' => [],
			'side_effects' => BrowserEventDocumentationHelper::lines('Creates resource_tree folder rows as needed.'),
		];
	}

	public function run(): void
	{
		$path = trim((string) Request::_POST('path', ''));

		if ($path === '') {
			ApiResponse::renderError('MISSING_PATH', 'path is required.', 400);

			return;
		}

		try {
			$folder_id = CmsResourceSpecService::upsertFolder(['path' => $path]);

			ApiResponse::renderSuccess([
				'folder_id' => $folder_id,
				'resource' => CmsResourceSpecService::exportFolderSpec($path),
			]);
		} catch (Throwable $exception) {
			ApiResponse::renderError('RESOURCE_CREATE_FOLDER_FAILED', $exception->getMessage(), 400);
		}
	}
}
