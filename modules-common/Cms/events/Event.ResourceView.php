<?php

class EventResourceView extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return PolicyDecision::allow();
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

			$resource->view();
		} else {
			self::_setCacheHeaders($resource);
			$resource->view();
		}
	}
}
