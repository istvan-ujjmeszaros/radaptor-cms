<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HtmxAuthorizationGuardTest extends TestCase
{
	public function testResourceMoveRequiresCreateOnDestinationAndEditOnMovedSubtree(): void
	{
		$source = $this->source('modules-common/Cms/classes/class.ResourceTreeHandler.php');

		$this->assertStringContainsString('getResourceMoveAclError($resource_id, $parent_id)', $source);
		$this->assertStringContainsString('ResourceAcl::canAccessResource($parent_id, ResourceAcl::_ACL_CREATE)', $source);
		$this->assertStringContainsString('ResourceAcl::canAccessResource($resource_id, ResourceAcl::_ACL_EDIT)', $source);
		$this->assertStringContainsString("NestedSet::getDescendants('resource_tree', \$resource_id", $source);
		$this->assertStringContainsString('ResourceAcl::canAccessResource($node_id, ResourceAcl::_ACL_EDIT)', $source);
		$this->assertStringContainsString('RESOURCE_MOVE_DENIED', $source);
		$this->assertLessThan(
			strpos($source, "NestedSet::moveToPosition('resource_tree'"),
			strpos($source, 'getResourceMoveAclError($resource_id, $parent_id)')
		);
	}

	public function testResourceDetailFragmentChecksAclBeforeLoadingResourceData(): void
	{
		$source = $this->source('modules-common/Cms/events/JstreeResourcesAjax/Event.JstreeResourcesAjaxDinaContent.php');

		$this->assertStringContainsString('JsTreeApiService::normalizeIds(Request::postRequired(\'id\'))', $source);
		$this->assertStringContainsString('RESOURCE_ACCESS_DENIED', $source);
		$this->assertStringContainsString('ResourceAcl::canAccessResource($resource_id, ResourceAcl::_ACL_LIST)', $source);
		$this->assertLessThan(
			strpos($source, 'ResourceTypeFactory::Factory($id)'),
			strpos($source, 'canRenderResourceDetails($id)')
		);
	}

	public function testAdminMenuAjaxEndpointsRequireDeveloperRole(): void
	{
		foreach ($this->adminMenuEventPaths() as $path) {
			$source = $this->source($path);

			$this->assertStringContainsString('hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)', $source, $path);
			$this->assertStringNotContainsString('SYSTEMUSERGROUP_LOGGEDIN', $source, $path);
			$this->assertStringNotContainsString('ROLE_SYSTEM_ADMINISTRATOR', $source, $path);
		}
	}

	public function testMainMenuAjaxEndpointsRequireContentAdminOrDeveloperRole(): void
	{
		foreach ($this->mainMenuEventPaths() as $path) {
			$source = $this->source($path);

			$this->assertStringContainsString('hasRole(RoleList::ROLE_CONTENT_ADMIN)', $source, $path);
			$this->assertStringContainsString('hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)', $source, $path);
			$this->assertStringNotContainsString('SYSTEMUSERGROUP_LOGGEDIN', $source, $path);
			$this->assertStringNotContainsString('ROLE_SYSTEM_ADMINISTRATOR', $source, $path);
		}
	}

	public function testPublicMainMenuFiltersInternalItemsByResourceViewAcl(): void
	{
		$source = $this->source('modules-common/MainMenu/classes/class.MainMenu.php');

		$this->assertStringContainsString('filterMenuDataByAcl', $source);
		$this->assertStringContainsString('ResourceAcl::canAccessResource($page_id, ResourceAcl::_ACL_VIEW)', $source);
		$this->assertStringContainsString('($menu[\'url\'] ?? null) !== null', $source);
		$this->assertLessThan(
			strpos($source, '$current_page_path = $tree_build_context->getPagedata(\'path\');'),
			strpos($source, 'filterMenuDataByAcl(DbHelper::fetchAll')
		);
	}

	public function testFragmentWidgetTargetIsScopedToResolvedPageAndWidgetCanAccessIsFinal(): void
	{
		$fragment_renderer = $this->source('modules-common/Cms/classes/class.CmsFragmentRenderer.php');
		$abstract_widget = $this->source('modules-common/Cms/classes/class.AbstractWidget.php');

		$this->assertStringContainsString('(int)($connection_data[\'page_id\'] ?? 0) !== (int)$this->view->getPageId()', $fragment_renderer);
		$this->assertStringContainsString('$this->treeBuilder->buildWidgetTargetTree($connection)', $fragment_renderer);
		$this->assertStringContainsString('final public function buildTree(', $abstract_widget);
		$this->assertLessThan(
			strpos($abstract_widget, 'return $this->buildAuthorizedTree('),
			strpos($abstract_widget, '$this->canAccess($tree_build_context, $connection)')
		);
	}

	/**
	 * @return list<string>
	 */
	private function adminMenuEventPaths(): array
	{
		return [
			'modules-common/AdminMenu/events/JstreeAdminMenuAjax/Event.JstreeAdminMenuAjaxLoad.php',
			'modules-common/AdminMenu/events/JstreeAdminMenuAjax/Event.JstreeAdminMenuAjaxDinaContent.php',
			'modules-common/AdminMenu/events/JstreeAdminMenuAjax/Event.JstreeAdminMenuAjaxMove.php',
			'modules-common/AdminMenu/events/JstreeAdminMenuAjax/Event.JstreeAdminMenuAjaxDeleteRecursive.php',
			'modules-common/AdminMenu/events/JstreeAdminMenuAjax/Event.JstreeAdminMenuAjaxRename.php',
		];
	}

	/**
	 * @return list<string>
	 */
	private function mainMenuEventPaths(): array
	{
		return [
			'modules-common/MainMenu/events/Event.JstreeMainMenuAjaxLoad.php',
			'modules-common/MainMenu/events/Event.JstreeMainMenuAjaxDinaContent.php',
			'modules-common/MainMenu/events/Event.JstreeMainMenuAjaxMove.php',
			'modules-common/MainMenu/events/Event.JstreeMainMenuAjaxDeleteRecursive.php',
			'modules-common/MainMenu/events/Event.JstreeMainMenuAjaxRename.php',
		];
	}

	private function source(string $relative_path): string
	{
		$path = dirname(__DIR__) . '/' . $relative_path;
		$this->assertFileExists($path);

		return (string) file_get_contents($path);
	}
}
