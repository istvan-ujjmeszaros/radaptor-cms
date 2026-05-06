<?php

declare(strict_types=1);

class CmsRenderVersion
{
	/*
	 * This currently prepares page render invalidation for fragment/full-page
	 * cache keys. Fragment responses are cache-bypassed in v1, and the legacy
	 * persistent page cache still relies on its short TTL.
	 */
	public static function touchWebpage(int $page_id): void
	{
		if ($page_id <= 0) {
			return;
		}

		$resource_data = ResourceTreeHandler::getResourceTreeEntryDataById($page_id);

		if (!is_array($resource_data) || ($resource_data['node_type'] ?? null) !== 'webpage') {
			return;
		}

		AbstractResourceType::updateLastModified($page_id);
	}

	public static function touchWidgetConnection(int $connection_id): void
	{
		$page_id = WidgetConnection::getOwnerWebpageId($connection_id);

		if ($page_id !== null) {
			self::touchWebpage($page_id);
		}
	}

	public static function touchForAttributeResource(AttributeResourceIdentifier $resource): void
	{
		if (!$resource->isValid()) {
			return;
		}

		$resource_id = (int)$resource->id;

		if ($resource_id <= 0) {
			return;
		}

		if ($resource->name === ResourceNames::RESOURCE_DATA) {
			self::touchWebpage($resource_id);

			return;
		}

		self::touchWidgetConnection($resource_id);
	}
}
