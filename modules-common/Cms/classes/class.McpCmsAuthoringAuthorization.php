<?php

declare(strict_types=1);

class McpCmsAuthoringAuthorization
{
	public static function loggedIn(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->inGroup(Usergroups::SYSTEMUSERGROUP_LOGGEDIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny('login required');
	}

	public static function systemDeveloper(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)
			? PolicyDecision::allow('role: system_developer')
			: PolicyDecision::deny('role required: system_developer');
	}

	public static function resourcePath(string $path, string $operation, PolicyContext $policyContext): PolicyDecision
	{
		$logged_in = self::loggedIn($policyContext);

		if (!$logged_in->allow) {
			return $logged_in;
		}

		$resource = CmsPathHelper::resolveResource($path);

		if (!is_array($resource)) {
			return PolicyDecision::deny("resource not found: {$path}");
		}

		return ResourceAcl::canAccessResource((int) $resource['node_id'], $operation)
			? PolicyDecision::allow()
			: PolicyDecision::deny("resource {$operation} denied: {$path}");
	}

	public static function webpagePath(string $path, string $operation, PolicyContext $policyContext): PolicyDecision
	{
		$logged_in = self::loggedIn($policyContext);

		if (!$logged_in->allow) {
			return $logged_in;
		}

		$page = CmsPathHelper::resolveWebpage($path);

		if (!is_array($page)) {
			return PolicyDecision::deny("webpage not found: {$path}");
		}

		if (($page['node_type'] ?? null) !== 'webpage') {
			return PolicyDecision::deny("resource is not a webpage: {$path}");
		}

		return ResourceAcl::canAccessResource((int) $page['node_id'], $operation)
			? PolicyDecision::allow()
			: PolicyDecision::deny("webpage {$operation} denied: {$path}");
	}

	public static function widgetConnection(int $connection_id, string $operation, PolicyContext $policyContext): PolicyDecision
	{
		$logged_in = self::loggedIn($policyContext);

		if (!$logged_in->allow) {
			return $logged_in;
		}

		if ($connection_id <= 0) {
			return PolicyDecision::deny('widget connection id is required');
		}

		$connection = Widget::getConnectionData($connection_id);

		if (!is_array($connection)) {
			return PolicyDecision::deny("widget connection not found: {$connection_id}");
		}

		$page_id = (int) ($connection['page_id'] ?? 0);

		if ($page_id <= 0) {
			return PolicyDecision::deny("widget connection has no owning webpage: {$connection_id}");
		}

		return ResourceAcl::canAccessResource($page_id, $operation)
			? PolicyDecision::allow()
			: PolicyDecision::deny("owning webpage {$operation} denied for widget connection: {$connection_id}");
	}

	public static function createWebpage(string $path, PolicyContext $policyContext): PolicyDecision
	{
		$logged_in = self::loggedIn($policyContext);

		if (!$logged_in->allow) {
			return $logged_in;
		}

		$parts = CmsPathHelper::splitWebpagePath($path);
		$parent = self::resolveNearestExistingFolder($parts['folder']);

		if (!is_array($parent)) {
			return PolicyDecision::deny("no existing ancestor folder found for: {$parts['folder']}");
		}

		return ResourceAcl::canAccessResource((int) $parent['node_id'], ResourceAcl::_ACL_CREATE)
			? PolicyDecision::allow()
			: PolicyDecision::deny("resource create denied: " . ResourceTreeHandler::getPathFromId((int) $parent['node_id']));
	}

	public static function createFolder(string $path, PolicyContext $policyContext): PolicyDecision
	{
		$logged_in = self::loggedIn($policyContext);

		if (!$logged_in->allow) {
			return $logged_in;
		}

		$parts = CmsPathHelper::splitFolderPath($path);
		$parent = self::resolveNearestExistingFolder($parts['parent_path']);

		if (!is_array($parent)) {
			return PolicyDecision::deny("no existing ancestor folder found for: {$parts['parent_path']}");
		}

		return ResourceAcl::canAccessResource((int) $parent['node_id'], ResourceAcl::_ACL_CREATE)
			? PolicyDecision::allow()
			: PolicyDecision::deny("resource create denied: " . ResourceTreeHandler::getPathFromId((int) $parent['node_id']));
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function resolveNearestExistingFolder(string $path): ?array
	{
		$normalized_path = CmsPathHelper::splitFolderPath($path)['normalized_path'];
		$segments = array_values(array_filter(
			explode('/', trim($normalized_path, '/')),
			static fn (string $segment): bool => $segment !== ''
		));

		for ($length = count($segments); $length >= 0; --$length) {
			$candidate = $length === 0
				? '/'
				: '/' . implode('/', array_slice($segments, 0, $length)) . '/';
			$folder = CmsPathHelper::resolveFolder($candidate);

			if (is_array($folder)) {
				return $folder;
			}
		}

		return null;
	}
}
