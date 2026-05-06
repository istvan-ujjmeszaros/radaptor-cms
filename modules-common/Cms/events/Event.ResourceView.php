<?php

class EventResourceView extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return PolicyDecision::allow();
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'resource.view',
			'group' => 'Runtime',
			'name' => 'Render resource',
			'summary' => 'Primary browser entrypoint that resolves the current resource and renders it or an access fallback.',
			'description' => 'Handles normal page/file requests, applies resource ACL checks, and may render the configured login page or a 403/404 response instead of the requested resource.',
			'request' => [
				'method' => 'GET',
				'params' => [],
			],
			'response' => [
				'kind' => 'resource-output',
				'content_type' => 'varies by resolved resource',
				'description' => 'Renders webpage HTML, streams a file response, or emits a 403/404 fallback.',
			],
			'authorization' => [
				'visibility' => 'public route with per-resource ACL',
				'description' => 'The event itself is public, but the resolved resource may still be denied by resource ACL.',
			],
			'notes' => BrowserEventDocumentationHelper::lines(
				'This is the default browser route. Url::getUrl(\'resource.view\') resolves to the site root.',
				'Anonymous access to a protected webpage may render the login page as a same-URL fallback.'
			),
			'side_effects' => BrowserEventDocumentationHelper::lines(
				'May toggle persistent-cache write behavior depending on whether the requested resource itself is viewable.'
			),
		];
	}

	private static function _setCacheHeaders(AbstractResourceType $resource): void
	{
		if (!$resource instanceof ResourceTypeFile) {
			ResourceTreeHandler::setNoCacheHeaders();

			return;
		}

		$lastModifyTime = $resource->getLastModified();

		$last_modified = gmdate('D, d M Y H:i:s \G\M\T', $lastModifyTime);

		header('Last-Modified: ' . $last_modified);
		header('Pragma: public');
		$if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? stripslashes((string) $_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;

		if ($if_modified_since === $last_modified) {
			header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');

			exit;
		}
	}

	public function run(): void
	{
		$resource = null;
		$resource_id = ResourceTreeHandler::getResourceTreeEntryIdFromUrl();
		$request_resource_is_viewable = false;

		self::redirectFolderResourceToCanonicalPath($resource_id);

		if ($resource_id > 0) {
			$request_resource_is_viewable = ResourceTreeHandler::canAccessResource($resource_id, ResourceAcl::_ACL_VIEW);

			if (!$request_resource_is_viewable) {
				if (is_null(User::getCurrentUser())) {
					$resource = ResourceTypeFactory::Factory(ResourceTypeWebpage::getWebpageIdByFormType(FormList::USERLOGIN));

					if ($resource instanceof ResourceTypeWebpage) {
						$resource->getView()->setEditMode(false);
						WebpageView::header(WebpageView::_HEADER_403);
						//header('HTTP/1.0 403 Forbidden', true, 403);
					} else {
						Kernel::abort(t('cms.error.login_page_missing'));
					}
				} else {
					ResourceTreeHandler::drop403();
				}
			} else {
				$resource = ResourceTypeFactory::Factory($resource_id);
			}
		}

		if (is_null($resource)) {
			ResourceTreeHandler::drop404();
		}

		// Persistent cache write is allowed only when the requested resource itself is viewable.
		// This prevents caching fallback pages (e.g. login page rendered for a forbidden request).
		if ($request_resource_is_viewable) {
			RequestContextHolder::enablePersistentCacheWrite();
		} else {
			RequestContextHolder::disablePersistentCacheWrite();
		}

		if (User::getCurrentUser() && $resource instanceof ResourceTypeWebpage) {
			self::_setCacheHeaders($resource);

			if ($resource->getView()->isHtmlOutputChannel()) {
				SystemMessages::setSystemMessagesDependencies($resource->getView());
			}

			if ($this->renderFragmentIfRequested($resource)) {
				return;
			}

			$resource->view();
		} else {
			self::_setCacheHeaders($resource);

			if ($resource instanceof ResourceTypeWebpage && $this->renderFragmentIfRequested($resource)) {
				return;
			}
			$resource->view();
		}
	}

	private function renderFragmentIfRequested(ResourceTypeWebpage $resource): bool
	{
		$server = RequestContextHolder::current()->SERVER ?: $_SERVER;
		$hx_request = strtolower(trim((string)($server['HTTP_HX_REQUEST'] ?? $server['http_hx_request'] ?? '')));
		$hx_boosted = strtolower(trim((string)($server['HTTP_HX_BOOSTED'] ?? $server['http_hx_boosted'] ?? '')));
		$is_fragment_context = (string)Request::_GET('context', '') === 'fragment';
		$is_boosted_fragment_request = $hx_request === 'true' && $hx_boosted === 'true';

		if (!$is_fragment_context && !$is_boosted_fragment_request) {
			return false;
		}

		RequestContextHolder::disablePersistentCacheWrite();

		if (!$resource->getView()->getLayoutType() instanceof iPartialNavigableLayout) {
			if ($hx_request === 'true') {
				header('HX-Redirect: ' . $this->canonicalCurrentUrl());
			} else {
				http_response_code(400);
			}

			return true;
		}

		try {
			$targets = Request::_GET('targets', []);
			$targets = is_array($targets) ? array_values(array_map('strval', $targets)) : [];

			WebpageView::header('Content-Type: text/html; charset=UTF-8');
			WebpageView::header('HX-Reswap: none');
			$renderer = new CmsFragmentRenderer($resource);
			echo $is_fragment_context ? $renderer->renderTargets($targets) : $renderer->renderDefaultPageFragment();
		} catch (Throwable) {
			if ($hx_request === 'true') {
				header('HX-Redirect: ' . $this->canonicalCurrentUrl());
			} else {
				http_response_code(400);
			}
		}

		return true;
	}

	private function canonicalCurrentUrl(): string
	{
		$params = Request::getGET();
		unset($params['context'], $params['event'], $params['targets']);
		$path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
		$query = http_build_query($params);

		return $query !== '' ? $path . '?' . $query : $path;
	}

	private static function redirectFolderResourceToCanonicalPath(?int $resource_id): void
	{
		if ($resource_id === null || $resource_id <= 0 || !in_array(Request::getMethod(), ['GET', 'HEAD'], true)) {
			return;
		}

		$resource_data = ResourceTreeHandler::getResourceTreeEntryDataById($resource_id);

		if (!is_array($resource_data) || ($resource_data['node_type'] ?? null) !== 'folder') {
			return;
		}

		$server = RequestContextHolder::current()->SERVER;
		$request_uri = (string) ($server['REQUEST_URI'] ?? $_SERVER['REQUEST_URI'] ?? '');
		$request_path = parse_url($request_uri, PHP_URL_PATH);

		if (!is_string($request_path) || $request_path === '' || str_ends_with($request_path, '/')) {
			return;
		}

		$target_path = self::buildCanonicalFolderPath($resource_data);

		if ($target_path === $request_path) {
			return;
		}

		$query = parse_url($request_uri, PHP_URL_QUERY);
		$target = $target_path . (is_string($query) && $query !== '' ? "?{$query}" : '');

		Url::redirect($target);
	}

	/**
	 * @param array<string, mixed> $resource_data
	 */
	private static function buildCanonicalFolderPath(array $resource_data): string
	{
		$path = (string) ($resource_data['path'] ?? '/');
		$resource_name = (string) ($resource_data['resource_name'] ?? '');
		$target_path = str_replace('//', '/', '/' . trim($path . $resource_name, '/') . '/');

		return $target_path === '//' ? '/' : $target_path;
	}
}
