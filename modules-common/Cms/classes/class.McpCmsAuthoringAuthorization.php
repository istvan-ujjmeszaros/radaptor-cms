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

		return ResourceAcl::canAccessResource((int) $page['node_id'], $operation)
			? PolicyDecision::allow()
			: PolicyDecision::deny("webpage {$operation} denied: {$path}");
	}

	public static function createWebpage(string $path, PolicyContext $policyContext): PolicyDecision
	{
		$logged_in = self::loggedIn($policyContext);

		if (!$logged_in->allow) {
			return $logged_in;
		}

		$parts = CmsPathHelper::splitWebpagePath($path);
		$parent = CmsPathHelper::resolveFolder($parts['folder']);

		if (!is_array($parent)) {
			return PolicyDecision::deny("parent folder not found: {$parts['folder']}");
		}

		return ResourceAcl::canAccessResource((int) $parent['node_id'], ResourceAcl::_ACL_CREATE)
			? PolicyDecision::allow()
			: PolicyDecision::deny("resource create denied: {$parts['folder']}");
	}

	public static function createFolder(string $path, PolicyContext $policyContext): PolicyDecision
	{
		$logged_in = self::loggedIn($policyContext);

		if (!$logged_in->allow) {
			return $logged_in;
		}

		$parts = CmsPathHelper::splitFolderPath($path);
		$parent = CmsPathHelper::resolveFolder($parts['parent_path']);

		if (!is_array($parent)) {
			return PolicyDecision::deny("parent folder not found: {$parts['parent_path']}");
		}

		return ResourceAcl::canAccessResource((int) $parent['node_id'], ResourceAcl::_ACL_CREATE)
			? PolicyDecision::allow()
			: PolicyDecision::deny("resource create denied: {$parts['parent_path']}");
	}
}
