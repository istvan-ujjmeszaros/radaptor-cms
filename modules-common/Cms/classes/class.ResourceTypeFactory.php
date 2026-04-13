<?php

class ResourceTypeFactory
{
	public static function Factory(int $resource_id): ?AbstractResourceType
	{
		$resource_data = ResourceTreeHandler::getResourceTreeEntryDataById($resource_id);

		if (is_null($resource_data)) {
			return null;
		}

		switch ($resource_data['node_type']) {
			case 'webpage':
			case 'folder':
				return new ResourceTypeWebpage($resource_id, $resource_data);

			case 'file':
				return new ResourceTypeFile($resource_id, $resource_data);

			case 'root':
				return new ResourceTypeRoot($resource_id, $resource_data);

			case '':
				return null;

			default:
				Kernel::abort("Unknown resource type: {$resource_data['node_type']}");
		}
	}
}
