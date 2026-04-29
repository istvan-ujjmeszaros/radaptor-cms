<?php

declare(strict_types=1);

class EventResourceAclSync extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		$path = trim((string) Request::_POST('path', ''));

		if ($path === '') {
			return PolicyDecision::deny('path is required');
		}

		return McpCmsAuthoringAuthorization::resourcePath($path, ResourceAcl::_ACL_EDIT, $policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'resource.acl_sync',
			'group' => 'CMS Authoring',
			'name' => 'Sync resource ACL',
			'summary' => 'Reconciles ACL settings for a resource.',
			'description' => 'Updates the local resource ACL from a JSON-shaped ACL spec.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('path', 'body', 'string', true, 'Resource path.'),
					BrowserEventDocumentationHelper::param('acl', 'body', 'json-object', true, 'ACL spec with inherit and usergroups keys.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns the reconciled local and resolved ACL state.',
			],
			'authorization' => [
				'visibility' => 'resource ACL',
				'description' => 'Requires edit permission on the resource.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.resource.acl_sync',
				'risk' => 'write',
			],
			'notes' => [],
			'side_effects' => BrowserEventDocumentationHelper::lines('Updates resource_acl rows and the resource ACL inheritance flag.'),
		];
	}

	public function run(): void
	{
		$path = trim((string) Request::_POST('path', ''));
		$acl = Request::_POST('acl', null);

		if ($path === '') {
			ApiResponse::renderError('MISSING_PATH', 'path is required.', 400);

			return;
		}

		if (!is_array($acl)) {
			ApiResponse::renderError('MISSING_ACL_SPEC', 'acl spec is required.', 400);

			return;
		}

		try {
			ApiResponse::renderSuccess(CmsResourceSpecService::syncAclForPath($path, $acl));
		} catch (Throwable $exception) {
			ApiResponse::renderError('RESOURCE_ACL_SYNC_FAILED', $exception->getMessage(), 400);
		}
	}
}
