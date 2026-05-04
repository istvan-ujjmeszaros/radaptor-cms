<?php

class ResourceTreeHandler extends ResourceAcl
{
	public const int MAXIMUM_ALLOWED_INLINE_SIZE_FOR_UNKNOWN = 10485760;   // 10MB

	private static array $_resizable_resources = [];

	public static function getPathFromId(int $resource_id): string
	{
		$data = ResourceTreeHandler::getResourceTreeEntryDataById($resource_id);

		if (is_null($data)) {
			return '';
		}

		$path = $data['path'] . $data['resource_name'];

		if ($path === '/') {
			return $path;
		}

		// Only add trailing slash for folders, not for files (which have extensions)
		$hasExtension = str_contains($data['resource_name'], '.');

		if ($hasExtension) {
			return $path;
		}

		return $path . '/';
	}

	public static function getFolderId(int $resource_id): int
	{
		$data = ResourceTreeHandler::getResourceTreeEntryDataById($resource_id);

		if ($data['node_type'] == 'folder') {
			return $data['node_id'];
		} else {
			return $data['parent_id'] != 0 ? ResourceTreeHandler::getFolderId((int)$data['parent_id']) : 0;
		}
	}

	public static function getPathVariations(string $folder): array
	{
		$exploded_folders = explode('/', $folder);

		foreach ($exploded_folders as $key => $folder) {
			if ($folder == '') {
				unset($exploded_folders[$key]);
			}
		}

		$exploded_folders = array_values($exploded_folders);

		$path_variations = [];

		for ($i = 0; $i < count($exploded_folders); ++$i) {
			$path_variation = '/';

			for ($j = 1; $j <= $i; ++$j) {
				$path_variation .= $exploded_folders[$j - 1] . '/';
			}

			$path_variations[] = [
				'path' => $path_variation,
				'resource_name' => $exploded_folders[$j - 1],
			];
		}

		return $path_variations;
	}

	public static function getResourceTree(int $parent_id): array
	{
		return self::filterChildrenByAcl(NestedSet::getChildren('resource_tree', $parent_id, [
			'resource_name',
			'node_type',
			'catcher_page',
		], 'node_type=\'folder\' DESC, lft ASC'));
	}

	public static function getResourceChildrenDetailed(int $parent_id): array
	{
		return self::filterChildrenByAcl(NestedSet::getChildren('resource_tree', $parent_id, [
			'resource_name',
			'node_type',
			'path',
			'is_inheriting_acl',
			'catcher_page',
		], 'node_type=\'folder\' DESC, lft ASC'));
	}

	public static function countChildren(int $parent_id): int
	{
		return (int) DbHelper::selectOneColumnFromQuery(
			"SELECT COUNT(1) FROM resource_tree WHERE parent_id=?",
			[$parent_id]
		);
	}

	public static function getParentNodes(int $resource_id): array
	{
		return NestedSet::getParentNodes('resource_tree', $resource_id) ?? [];
	}

	private static function filterChildrenByAcl(array $nodes): array
	{
		foreach ($nodes as $key => $value) {
			if (!ResourceAcl::canAccessResource($value['node_id'], ResourceAcl::_ACL_LIST)) {
				unset($nodes[$key]);
			}
		}

		return array_values($nodes);
	}

	public static function setDownloadHeader(int $file_size, string $save_name): void
	{
		WebpageView::header("Pragma: 0");
		WebpageView::header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		WebpageView::header("Cache-Control: private");
		WebpageView::header("Content-type: application/force-download");
		WebpageView::header("Content-Transfer-Encoding: Binary");
		WebpageView::header("Content-length: $file_size");
		WebpageView::header("Content-disposition: attachment; filename=\"$save_name\"");
	}

	public static function setInlineHeader(int $file_size, string $save_name, string $mime = 'unknown'): void
	{
		if ($mime == 'unknown') {
			if ($file_size > self::MAXIMUM_ALLOWED_INLINE_SIZE_FOR_UNKNOWN) {
				self::setDownloadHeader($file_size, $save_name);

				return;
			}
		} else {
			WebpageView::header("Content-type: {$mime}");
		}

		WebpageView::header("Content-Transfer-Encoding: Binary");
		WebpageView::header("Content-length: {$file_size}");
		WebpageView::header("Content-disposition: inline; filename=\"{$save_name}\"");
		WebpageView::header('Accept-Ranges: bytes');
	}

	public static function getResourceTreeEntryIdFromUrl(?string $domain_context = null): ?int
	{
		$domain_context = self::getActiveDomainContext($domain_context);

		$folder = Request::_GET('folder');
		$resource = Request::_GET('resource');

		$node_id = self::_getResourceTreeEntryIdFromNamedResource($folder, $resource, $domain_context);

		// átméretezett képek, pl /mappa/kep.150x150.jpg == /mappa/kep.jpg(átméretezve 150x150-re)
		if (is_null($node_id)) {
			$node_data = self::_getResizableResourceTreeEntryIdFromNamedResource($folder, $resource, $domain_context);

			if ($node_data) {
				self::$_resizable_resources[$node_data['node_id']] = $node_data['resize_data'];
				$node_id = $node_data['node_id'];
			}
		}

		// elfogó oldal keresése
		if (is_null($node_id)) {
			$node_id = self::_getClosestCatchableResourceTreeEntryId($folder, $domain_context);
		}

		return $node_id;
	}

	public static function getResourceTreeEntryDataById(int $resource_id): ?array
	{
		$cached = Cache::get(self::class, $resource_id);

		if (!is_null($cached)) {
			return $cached;
		}

		$return = NestedSet::getNodeInfo('resource_tree', $resource_id);

		return Cache::set(self::class, $resource_id, $return);
	}

	public static function getDomainContextForResourceTreeEntryData(array $resource_data): string
	{
		if (($resource_data['node_type'] ?? null) === 'root') {
			return (string) ($resource_data['resource_name'] ?? '');
		}

		$query = "SELECT resource_name FROM resource_tree WHERE lft < ? AND rgt > ? ORDER BY lft LIMIT 1";

		return DbHelper::selectOneColumnFromQuery(
			$query,
			[$resource_data['lft'], $resource_data['rgt']]
		);
	}

	public static function getResourceTreeEntryData(string $folder, string $resource_name, ?string $domain_context = null): ?array
	{
		$domain_context = self::getActiveDomainContext($domain_context);

		$query_domain_context = "SELECT node_id FROM resource_tree WHERE node_type='root' AND resource_name=?";
		$stmt_domain_context = Db::instance()->prepare($query_domain_context);
		$stmt_domain_context->execute([$domain_context]);

		$rs_domain_context = $stmt_domain_context->fetch(PDO::FETCH_ASSOC);

		if ($rs_domain_context === false) {
			return null;
		}

		$node = self::getResourceTreeEntryDataById($rs_domain_context['node_id']);

		if (is_null($node)) {
			return null;
		}

		if ($folder !== '/') {
			$folder = str_replace('//', '/', '/' . $folder . '/');
		}

		$stmt = Db::instance()
				  ->prepare("SELECT * FROM resource_tree WHERE resource_name=? AND path=? AND lft > ? AND rgt < ? LIMIT 1");
		$stmt->execute([
			$resource_name,
			$folder,
			$node['lft'],
			$node['rgt'],
		]);

		$rs = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($rs === false) {
			return null;
		}

		return $rs;
	}

	private static function _getResourceTreeEntryIdFromNamedResource(string $folder, string $resource_name, ?string $domain_context = null): ?int
	{
		$domain_context = self::getActiveDomainContext($domain_context);

		$rs = self::getResourceTreeEntryData($folder, $resource_name, $domain_context);

		if (!is_array($rs)) {
			return null;
		}

		return $rs['node_id'];
	}

	private static function _getResizableResourceTreeEntryIdFromNamedResource(string $folder, string $resource_name, ?string $domain_context = null): ?array
	{
		$domain_context = self::getActiveDomainContext($domain_context);

		$exploded_resource_name = explode('.', $resource_name);

		if (count($exploded_resource_name) < 3) {
			return null;
		}

		$resize_data = $exploded_resource_name[count($exploded_resource_name) - 2];

		unset($exploded_resource_name[count($exploded_resource_name) - 2]);

		$genuine_resource_name = implode('.', $exploded_resource_name);

		$rs = self::getResourceTreeEntryData($folder, $genuine_resource_name, $domain_context);

		if (is_null($rs)) {
			return null;
		}

		return [
			'node_id' => $rs['node_id'],
			'resize_data' => $resize_data,
		];
	}

	public static function getResizeTreeEntryData(int $node_id): string
	{
		return self::$_resizable_resources[$node_id] ?? '';
	}

	private static function _getClosestCatchableResourceTreeEntryId(string $folder, ?string $domain_context = null): ?int
	{
		$domain_context = self::getActiveDomainContext($domain_context);

		$path_variations = self::getPathVariations($folder);
		$path_variations = array_reverse($path_variations);

		foreach ($path_variations as $path_variation) {
			$data = self::getResourceTreeEntryData($path_variation['path'], $path_variation['resource_name'], $domain_context);

			if (isset($data['catcher_page']) && $data['catcher_page'] > 0) {
				return $data['catcher_page'];
			}
		}

		$root_id = self::getDomainRoot($domain_context);

		if ($root_id === null) {
			return null;
		}

		$root = self::getResourceTreeEntryDataById($root_id);

		if (!is_array($root)) {
			return null;
		}

		if ($root['catcher_page']) {
			return $root['catcher_page'];
		}

		return null;
	}

	public static function getDomainRoot(string $domain): ?int
	{
		$query = "SELECT node_id FROM resource_tree WHERE node_type='root' AND resource_name=? LIMIT 1";

		$root_id = DbHelper::selectOneColumnFromQuery(
			$query,
			[$domain]
		);

		if ($root_id === false || $root_id === null) {
			return null;
		}

		return (int) $root_id;
	}

	public static function getActiveDomainContext(?string $domain_context = null): string
	{
		$domain_context = trim((string) $domain_context);

		if ($domain_context !== '') {
			return $domain_context;
		}

		return CmsSiteContext::resolve();
	}

	public static function createResourceTreeEntryFromPath(string $folder, string $resource_name, string $resource_type, ?string $layout_name = null, ?string $domain_context = null): ?int
	{
		$path_variations = self::getPathVariations($folder);

		// Create any missing folders for the requested path first.
		$last_parent_id = self::ensureDomainRoot(self::getActiveDomainContext($domain_context));

		if (is_null($last_parent_id)) {
			return null;
		}

		foreach ($path_variations as $path_variation) {
			$page_data = self::getResourceTreeEntryData($path_variation['path'], $path_variation['resource_name'], $domain_context);

			if (is_null($page_data)) {
				$savedata = [
					'node_type' => 'folder',
					'path' => $path_variation['path'],
					'resource_name' => $path_variation['resource_name'],
				];

				$created_id = self::addResourceEntry($savedata, $last_parent_id);

				if (!is_numeric($created_id) || (int) $created_id <= 0) {
					return null;
				}

				$last_parent_id = (int) $created_id;
			} elseif ($page_data['node_type'] == $resource_type) {
				// Invalid path: a webpage and a folder cannot share the same name
				// under the same parent.
				return null;
			} else {
				$last_parent_id = (int) $page_data['node_id'];
			}
		}

		// Then create the webpage itself.
		$savedata = [
			'node_type' => 'webpage',
			'path' => $folder,
			'resource_name' => $resource_name,
			'layout' => $layout_name,
		];

		$created_webpage_id = self::addResourceEntry($savedata, $last_parent_id);

		if (!is_numeric($created_webpage_id) || (int) $created_webpage_id <= 0) {
			return null;
		}

		return (int) $created_webpage_id;
	}

	public static function createFolderFromPath(string $path, ?string $domain_context = null): ?int
	{
		$normalized_path = CmsPathHelper::splitFolderPath($path)['normalized_path'];

		if ($normalized_path === '/') {
			return self::ensureDomainRoot(self::getActiveDomainContext($domain_context));
		}

		$path_variations = self::getPathVariations($normalized_path);
		$last_parent_id = self::ensureDomainRoot(self::getActiveDomainContext($domain_context));

		if (is_null($last_parent_id)) {
			return null;
		}

		foreach ($path_variations as $path_variation) {
			$folder_data = self::getResourceTreeEntryData($path_variation['path'], $path_variation['resource_name'], $domain_context);

			if ($folder_data === null) {
				$last_parent_id = self::addResourceEntry([
					'node_type' => 'folder',
					'path' => $path_variation['path'],
					'resource_name' => $path_variation['resource_name'],
				], $last_parent_id);

				if (!is_numeric($last_parent_id) || (int) $last_parent_id <= 0) {
					return null;
				}

				$last_parent_id = (int) $last_parent_id;

				continue;
			}

			if (($folder_data['node_type'] ?? null) !== 'folder') {
				return null;
			}

			$last_parent_id = (int) $folder_data['node_id'];
		}

		return $last_parent_id;
	}

	public static function ensureConfiguredSiteRoot(): ?int
	{
		$site_key = CmsSiteContext::getConfiguredSiteKey();
		$configured_root = CmsSiteContext::getRootByName($site_key);
		$content_roots = CmsSiteContext::getContentRootRows();
		$has_explicit_aliases = CmsSiteContext::hasExplicitHostAliasConfig();

		if (!$has_explicit_aliases && count($content_roots) > 1) {
			throw CmsSiteContext::ambiguousSiteRootsException($content_roots);
		}

		if (is_array($configured_root) && self::countChildren((int) $configured_root['node_id']) > 0) {
			return (int) $configured_root['node_id'];
		}

		if ($has_explicit_aliases && count($content_roots) > 1) {
			$matching_root = self::getSingleConfiguredAliasContentRoot($site_key, $content_roots);

			if (is_array($matching_root)) {
				return self::renameContentRootToSiteKey($matching_root, $site_key, $configured_root);
			}

			throw CmsSiteContext::ambiguousSiteRootsException($content_roots);
		}

		if (count($content_roots) === 1) {
			return self::renameContentRootToSiteKey($content_roots[0], $site_key, $configured_root);
		}

		if (is_array($configured_root)) {
			return (int) $configured_root['node_id'];
		}

		return self::ensureDomainRoot($site_key);
	}

	/**
	 * @param list<array<string, mixed>> $content_roots
	 * @return array<string, mixed>|null
	 */
	private static function getSingleConfiguredAliasContentRoot(string $site_key, array $content_roots): ?array
	{
		$candidate_root_names = array_values(array_unique(array_filter([
			$site_key,
			...CmsSiteContext::getHostsForSite($site_key),
		], static fn (string $name): bool => $name !== '')));

		$matches = array_values(array_filter(
			$content_roots,
			static fn (array $root): bool => in_array((string) ($root['resource_name'] ?? ''), $candidate_root_names, true)
		));

		return count($matches) === 1 ? $matches[0] : null;
	}

	/**
	 * @param array<string, mixed>      $content_root
	 * @param array<string, mixed>|null $configured_root
	 */
	private static function renameContentRootToSiteKey(array $content_root, string $site_key, ?array $configured_root): int
	{
		$content_root_id = (int) $content_root['node_id'];
		$content_root_name = (string) $content_root['resource_name'];

		if ($content_root_name === $site_key) {
			return $content_root_id;
		}

		if (is_array($configured_root) && (int) $configured_root['node_id'] !== $content_root_id) {
			if (!self::deleteEmptySiteRoot((int) $configured_root['node_id'])) {
				throw new RuntimeException("Unable to delete empty configured site root before normalizing {$content_root_name} to {$site_key}.");
			}
		}

		self::updateResourceTreeEntry([
			'resource_name' => $site_key,
		], $content_root_id);

		if (self::getDomainRoot($site_key) !== $content_root_id) {
			throw new RuntimeException("Unable to normalize CMS site root from {$content_root_name} to {$site_key}.");
		}

		SystemMessages::_warning("CMS site root normalized from {$content_root_name} to {$site_key}");

		return $content_root_id;
	}

	public static function deleteEmptySiteRoot(int $root_id): bool
	{
		$root = self::getResourceTreeEntryDataById($root_id);

		if (!is_array($root) || (string) ($root['node_type'] ?? '') !== 'root' || (int) ($root['parent_id'] ?? -1) !== 0) {
			throw new InvalidArgumentException("Resource {$root_id} is not a site root.");
		}

		if (self::countChildren($root_id) > 0) {
			throw new InvalidArgumentException("Site root {$root_id} is not empty.");
		}

		Cache::flush();

		if (!NestedSet::deleteNode('resource_tree', $root_id)) {
			return false;
		}

		AttributeHandler::deleteAttributes(new AttributeResourceIdentifier(ResourceNames::RESOURCE_DATA, (string) $root_id));
		DbHelper::prexecute('DELETE FROM resource_acl WHERE resource_id=?', [$root_id]);

		return true;
	}

	private static function ensureDomainRoot(string $domain): ?int
	{
		$root_id = self::getDomainRoot($domain);

		if (!is_null($root_id)) {
			return (int) $root_id;
		}

		$existing_node_count = (int) DbHelper::selectOneColumnFromQuery(
			"SELECT COUNT(*) FROM resource_tree"
		);

		if ($existing_node_count === 0) {
			$savedata = [
				'node_type' => 'root',
				'resource_name' => $domain,
			];

			$root_id = self::addResourceEntry($savedata);

			if (!is_null($root_id)) {
				SystemMessages::_warning("Site root created: {$domain}");
			}

			return $root_id;
		}

		$existing_root_count = (int) DbHelper::selectOneColumnFromQuery(
			"SELECT COUNT(*) FROM resource_tree WHERE node_type='root'"
		);

		if ($existing_root_count > 0) {
			$root_id = self::addResourceEntry([
				'node_type' => 'root',
				'resource_name' => $domain,
			]);

			if (!is_null($root_id)) {
				SystemMessages::_warning("Site root created: {$domain}");
			}

			return $root_id;
		}

		return self::wrapExistingTreeWithDomainRoot($domain);
	}

	private static function wrapExistingTreeWithDomainRoot(string $domain): ?int
	{
		try {
			$root_id = NestedSet::wrapExistingTreeWithRoot('resource_tree', [
				'node_type' => 'root',
				'resource_name' => $domain,
				'path' => '/',
			]);
		} catch (Throwable $exception) {
			error_log("NestedSet wrapExistingTreeWithRoot failed for resource_tree: " . $exception->getMessage());
			SystemMessages::_error("Unable to wrap existing resource tree with site root: {$domain}");

			return null;
		}

		if (is_null($root_id)) {
			SystemMessages::_error("Unable to wrap existing resource tree with site root: {$domain}");

			return null;
		}

		self::rebuildPath($root_id);
		SystemMessages::_warning("Site root wrapped around existing rootless tree: {$domain}");

		return $root_id;
	}

	public static function drop404(string $message = ''): never
	{
		if (Kernel::getEnvironment() === 'test') {
			Kernel::abort('drop404: ' . $message);
		}

		WebpageView::header("HTTP/1.0 404 Not Found");
		echo "404 - NINCS ILYEN OLDAL!<br>\n" . $message;

		if ($message != '') {
			exit;
		}

		if (Config::DEV_APP_DEBUG_INFO->value() && Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)) {
			var_dump(Request::getGET());
			var_dump('CLI?:', defined('RADAPTOR_CLI'));

			if (defined('RADAPTOR_CLI')) {
				var_dump(EventResolver::getEventHandlerFromCommandline());
			} else {
				var_dump(EventResolver::getEventHandlerFromUrl());
			}

			echo "<pre style='background:#fff;color:#333;padding:10px;margin:10px;text-align:left;'>";
			echo "<strong>Stack trace:</strong>\n";
			debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			echo "</pre>";
		}

		if (!Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN)) {
			exit;
		}

		// WebpageView used only to resolve the theme and provide iTreeBuildContext
		$view = new WebpageView();
		$renderer = new HtmlTreeRenderer(
			theme: $view->getTheme(),
			lang_id: Kernel::getLocale(),
		);
		$renderer->registerLibrary('__ADMIN_SITE');
		$renderer->registerLibrary('SYSTEMMESSAGES');
		$renderer->registerLibrary('QTIP');
		$renderer->registerLibrary('WIDGETTYPE_FORM');

		// Build the form tree using $view as iTreeBuildContext
		$form = Form::factory('WebpageFromPath', 'wpfp', $view);
		$fragment = $renderer->render($form->buildTree());

		// Emit after render so any form-registered assets are included
		echo $renderer->getCss();
		echo $renderer->getJsTop();
		echo '
<style>
    img {
        display:inline-block;
        vertical-align:middle;
    }
</style>
<div class="content">
<div class="content-full">
';
		echo $fragment;

		echo $renderer->getJs();
		echo '
<script type="text/javascript">
    if (typeof renderSystemMessages === "function") {
        renderSystemMessages();
    }
</script>
';
		echo $renderer->fetchClosingHtml();

		exit;
	}

	public static function drop403(): never
	{
		if (Kernel::getEnvironment() === 'test') {
			Kernel::abort('drop403');
		}

		WebpageView::header("HTTP/1.0 403 Forbidden");
		echo e(t('cms.resource.view_forbidden')) . "<br>\n";

		exit;
	}

	public static function drop400(string $message = ''): never
	{
		if (Kernel::getEnvironment() === 'test') {
			Kernel::abort('drop400: ' . $message);
		}

		WebpageView::header("HTTP/1.1 400 Bad Request");
		echo "400 - BAD REQUEST!<br>\n";

		if (Kernel::getEnvironment() === 'development') {
			echo $message . "<br>\n";
		}

		exit;
	}

	public static function getResourceTreeEntryName(int $resource_id): string
	{
		$resource_data = self::getResourceTreeEntryDataById($resource_id);

		return is_array($resource_data) ? $resource_data['path'] . $resource_data['resource_name'] : '';
	}

	private static function sanitizeResourceTreeEntryNameInSavedata(array &$savedata, string $dsn = ''): void
	{
		if (isset($savedata['resource_name'])) {
			$savedata['resource_name'] = Helpers::sanitize($savedata['resource_name']);

			if ($savedata['resource_name'] === '') {
				$savedata['resource_name'] = DbHelper::getNextAutoIncrementId('resource_tree', $dsn);
			}
		}
	}

	/**
	 * Splits the provided data array into resource-related data and attribute-related data.
	 *
	 * This method takes an array of data and separates it into two arrays:
	 * one for resource-related data (such as 'node_id', 'resource_name', etc.)
	 * and another for the remaining attribute-related data. The result is an array
	 * containing both of these arrays.
	 *
	 * @param array $savedata The data to be split into resource and attribute data.
	 * @return array An array containing two arrays: the first with resource-related data and the second with attribute-related data.
	 */
	private static function splitResourceAndAttributesData(array $savedata): array
	{
		$resource_keys = array_flip([
			'node_id',
			'resource_name',
			'catcher_page',
			'comment',
			'node_type',
			'path',
			'is_inheriting_acl',
		]);

		$resource_savedata = array_intersect_key($savedata, $resource_keys);
		$attribute_savedata = array_diff_key($savedata, $resource_keys);

		return [
			$resource_savedata,
			$attribute_savedata,
		];
	}

	public static function updateResourceTreeEntry(array $savedata, int $resource_id): int
	{
		$structural_fields = NestedSet::getStructuralFieldsInSavedata($savedata);

		if ($structural_fields !== []) {
			throw new InvalidArgumentException('Resource tree structural fields must be changed through ResourceTreeHandler move/add/delete operations: ' . implode(', ', $structural_fields));
		}

		$resource_data = ResourceTreeHandler::getResourceTreeEntryDataById($resource_id);

		if (is_null($resource_data)) {
			return 0;
		}

		if (array_key_exists('resource_name', $savedata)) {
			self::sanitizeResourceTreeEntryNameInSavedata($savedata);
			self::makeResourceEntryNameUniqueInSavedata($resource_data['parent_id'], $savedata, $resource_id);
		}

		[$resource_savedata, $attribute_savedata] = self::splitResourceAndAttributesData($savedata);

		$resource_savedata['node_id'] = $resource_id;

		if (isset($attribute_savedata['catcher_page'])) {
			if ($attribute_savedata['catcher_page']) {
				self::setAsCatcherPage($resource_id);
			} else {
				self::clearCatcherPage($resource_id);
			}
		}

		$return = DbHelper::updateHelper('resource_tree', $resource_savedata);

		$return2 = AttributeHandler::addAttribute(new AttributeResourceIdentifier(ResourceNames::RESOURCE_DATA, (string) $resource_id), $attribute_savedata);

		self::rebuildPath($resource_savedata['node_id']);

		return $return + $return2;
	}

	public static function addResourceEntry(array $savedata, int $parent_id = 0): ?int
	{
		self::sanitizeResourceTreeEntryNameInSavedata($savedata);
		self::makeResourceEntryNameUniqueInSavedata($parent_id, $savedata);

		[$resource_savedata, $attribute_savedata] = self::splitResourceAndAttributesData($savedata);

		try {
			$new_id = NestedSet::addNode('resource_tree', $parent_id, $resource_savedata);
		} catch (Exception $e) {
			SystemMessages::_error($e->getMessage());

			return null;
		}

		if (!is_numeric($new_id) || (int) $new_id <= 0) {
			return null;
		}

		$new_id = (int) $new_id;

		AttributeHandler::addAttribute(
			new AttributeResourceIdentifier(ResourceNames::RESOURCE_DATA, (string) $new_id),
			$attribute_savedata
		);

		if (isset($attribute_savedata['catcher_page']) && $attribute_savedata['catcher_page']) {
			self::setAsCatcherPage($new_id);
		}

		self::rebuildPath($new_id);

		return $new_id;
	}

	private static function makeResourceEntryNameUniqueInSavedata(int $parent_id, array &$savedata, ?int $update_id = null): void
	{
		if (!array_key_exists('resource_name', $savedata)) {
			return;
		}

		if ($update_id == 0) {
			$update = false;
		} else {
			$update = true;
		}

		if ($update) {
			$query = <<<SQL
					SELECT
						node_id
					FROM
						resource_tree
					WHERE parent_id = ?
						AND node_id <> ?
						AND resource_name = ?
					LIMIT 1
				SQL;
		} else {
			$query = <<<SQL
					SELECT
						node_id
					FROM
						resource_tree
					WHERE parent_id = ?
						AND resource_name = ?
					LIMIT 1
				SQL;
		}

		$stmt = Db::instance()->prepare($query);

		$pathinfo = pathinfo((string)$savedata['resource_name']);
		$extension = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';

		$i = 0;

		do {
			$name = $pathinfo['filename'];

			if ($i > 0) {
				$name = $name . "({$i})";
			}

			if ($update) {
				$stmt->execute([
					$parent_id,
					$update_id,
					$name . $extension,
				]);
			} else {
				$stmt->execute([
					$parent_id,
					$name . $extension,
				]);
			}

			$rs = $stmt->fetchAll();
			++$i;
		} while (count($rs) > 0);

		$savedata['resource_name'] = $name . $extension;
	}

	public static function rebuildPath(int $from_node_id = 0): int
	{
		return NestedSet::rebuildPath('resource_tree', $from_node_id);
	}

	public static function deleteResourceEntry(int $resource_id): bool
	{
		if (!ResourceAcl::canAccessResource($resource_id, ResourceAcl::_ACL_DELETE)) {
			return false;
		}

		$resource_data = self::getResourceTreeEntryDataById($resource_id);

		if (!is_array($resource_data)) {
			return false;
		}

		$node_type = (string) ($resource_data['node_type'] ?? '');
		$attribute_resource = new AttributeResourceIdentifier(ResourceNames::RESOURCE_DATA, (string) $resource_id);
		$attributes = AttributeHandler::getAttributes($attribute_resource);
		$file_id = $node_type === 'file' ? (int) ($attributes['file_id'] ?? 0) : 0;

		self::clearCatcherPage($resource_id);

		Cache::flush();

		if (!NestedSet::deleteNode('resource_tree', $resource_id)) {
			return false;
		}

		AttributeHandler::deleteAttributes($attribute_resource);

		if ($node_type !== 'file' || $file_id <= 0) {
			return true;
		}

		if (self::hasResourceReferencesForFileId($file_id)) {
			return true;
		}

		if (FileContainer::getDataFromFileId($file_id) === false) {
			return true;
		}

		return FileContainer::delFile($file_id);
	}

	public static function deleteResourceEntriesRecursive(int $resource_id): array
	{
		$folder_count = 0;
		$webpage_count = 0;
		$file_count = 0;
		$erroneous_count = 0;

		// 0. lekérjük az adott id-jű node adatait
		$node_data = self::getResourceTreeEntryDataById($resource_id);

		if (!is_array($node_data)) {
			return ([
				'success' => false,
				'erroneous' => 1,
				'folder' => 0,
				'webpage' => 0,
				'file' => 0,
			]);
		}

		$lft = $node_data['lft'];
		$rgt = $node_data['rgt'];

		if ($rgt - $lft == 1) {
			$success = ResourceTreeHandler::deleteResourceEntry($resource_id);

			if ($success && $node_data['node_type'] == 'webpage') {
				++$webpage_count;
			} elseif ($success && $node_data['node_type'] == 'file') {
				++$file_count;
			} elseif ($success && $node_data['node_type'] == 'folder') {
				++$folder_count;
			} else {
				++$erroneous_count;
			}
		} else {
			// 1. Lekérjük az alatta lévő node-okat, (rgt-lft) szerint rendezve
			$stmt = Db::instance()
					  ->prepare("SELECT node_id, node_type, (rgt-lft) AS rgtlft FROM resource_tree WHERE lft >= ? AND rgt <= ? ORDER BY rgtlft ASC");
			$stmt->execute([
				$lft,
				$rgt,
			]);

			$rs = $stmt->fetchAll(PDO::FETCH_ASSOC);

			foreach ($rs as $node) {
				$data = ResourceTreeHandler::getResourceTreeEntryDataById($node['node_id']);

				if ($data['rgt'] - $data['lft'] != 1) {
					++$erroneous_count;

					continue;
				}

				switch ($node['node_type']) {
					case 'webpage':

						$success = ResourceTreeHandler::deleteResourceEntry($node['node_id']);

						if ($success) {
							++$webpage_count;
						} else {
							++$erroneous_count;
						}

						break;

					case 'file':

						$success = ResourceTreeHandler::deleteResourceEntry($node['node_id']);

						if ($success) {
							++$file_count;
						} else {
							++$erroneous_count;
						}

						break;

					case 'folder':
					default:

						$success = ResourceTreeHandler::deleteResourceEntry($node['node_id']);

						if ($success) {
							++$folder_count;
						} else {
							++$erroneous_count;
						}

						break;
				}
			}
		}

		if ($erroneous_count + $folder_count + $webpage_count + $file_count > 0) {
			$success = true;
		} else {
			$success = false;
		}

		return ([
			'success' => $success,
			'erroneous' => $erroneous_count,
			'folder' => $folder_count,
			'webpage' => $webpage_count,
			'file' => $file_count,
		]);
	}

	public static function getIndexpageNodeId(int $containing_folder_resource_id): ?int
	{
		return self::getPageInFolder($containing_folder_resource_id, 'index.html');
	}

	public static function getPageInFolder(int $containing_folder_resource_id, string $page_name): ?int
	{
		$query = "SELECT node_id FROM resource_tree WHERE parent_id=? AND resource_name=? LIMIT 1";
		$stmt = Db::instance()->prepare($query);
		$stmt->execute([
			$containing_folder_resource_id,
			$page_name,
		]);

		$rs = $stmt->fetch(PDO::FETCH_ASSOC);

		return $rs['node_id'] ?? null;
	}

	public static function setAsCatcherPage(int $resource_id): void
	{
		$page_data = self::getResourceTreeEntryDataById($resource_id);

		if (!$page_data) {
			return;
		}

		$parent_id = $page_data['parent_id'];

		/*
		// töröljük a korábbi elfogó oldalnál a beállítást
		$query = "UPDATE resource_tree SET catcher_page=NULL WHERE node_id IN (SELECT node_id FROM (SELECT node_id FROM resource_tree) WHERE node_type='webpage' AND parent_id=? AND node_id <> ?)";
		$stmt = Db::instance()->prepare($query);
		$stmt->execute(array($parent_id, $catcher_page_id));
		*/

		// frissítjük a szülő mappán az elfogó oldal azonosítót
		$query = "UPDATE resource_tree SET catcher_page=? WHERE node_id=?";
		$stmt = Db::instance()->prepare($query);
		$stmt->execute([
			$resource_id,
			$parent_id,
		]);

		Cache::flush();
	}

	public static function checkParentHasCatcherPage(int $resource_id): bool
	{
		$page_data = self::getResourceTreeEntryDataById($resource_id);

		if (is_null($page_data)) {
			return false;
		}

		$parent_id = $page_data['parent_id'];

		$parent_data = self::getResourceTreeEntryDataById($parent_id);

		if (is_null($parent_data)) {
			return false;
		}

		return $parent_data['catcher_page'] > 0;
	}

	public static function clearCatcherPage(int $resource_id): void
	{
		// töröljük a korábbi elfogó oldalnál a beállítást
		$query = "UPDATE resource_tree SET catcher_page=NULL WHERE node_type='folder' AND catcher_page=?";
		$stmt = Db::instance()->prepare($query);
		$stmt->execute([$resource_id]);

		Cache::flush();
	}

	public static function moveResourceEntryToPosition(int $resource_id, int $parent_id, int $position): bool
	{
		$move = NestedSet::moveToPosition('resource_tree', $resource_id, $parent_id, $position);

		self::rebuildPath($resource_id);

		return $move;
	}

	public static function getResourceListForSelect(string $resource_type, bool $use_index_html = true): array
	{
		$query = "SELECT * FROM resource_tree WHERE node_type=? ORDER BY path, resource_name";

		$stmt = Db::instance()->prepare($query);
		$stmt->execute([$resource_type]);

		$resource_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$return[] = [
			'inputtype' => 'option',
			'value' => '',
			'label' => '-- Ne legyen linkelve --',
		];

		foreach ($resource_list as $resource) {
			if (!ResourceAcl::canAccessResource($resource['node_id'], ResourceAcl::_ACL_LIST)) {
				continue;
			}

			if ($use_index_html || $resource['resource_name'] != 'index.html') {
				$label = $resource['path'] . '/' . $resource['resource_name'];
			} else {
				$label = $resource['path'] . '/';
			}

			$label = str_replace('//', '/', $label);
			$return[] = [
				'inputtype' => 'option',
				'value' => $resource['node_id'],
				'label' => $label,
			];
		}

		return $return;
	}

	public static function getResourceIdFromConnectionId(int $connection_id): ?int
	{
		return DbHelper::selectOneColumnFromQuery(
			"SELECT page_id FROM widget_connections WHERE connection_id=? LIMIT 1;",
			[$connection_id]
		);
	}

	public static function hasResourceReferencesForFileId(int $file_id): bool
	{
		$stmt = Db::instance()->prepare(
			"SELECT 1
			 FROM attributes
			 WHERE resource_name=?
			   AND param_name='file_id'
			   AND param_value=?
			 LIMIT 1"
		);
		$stmt->execute([
			ResourceNames::RESOURCE_DATA,
			(string) $file_id,
		]);

		return $stmt->fetch(PDO::FETCH_COLUMN) !== false;
	}

	public static function setNoCacheHeaders(): void
	{
		header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
	}
}
