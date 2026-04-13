<?php

/**
 * It is just a dummy class, when working with
 * root nodes (in admin site's resource tree).
 */
class ResourceTypeRoot extends AbstractResourceType
{
	protected iView $_view;

	public function __construct(int $resource_id, array $resource_data)
	{
		$this->_resourceId = $resource_id;
		$this->_resourceData = $resource_data;
		$this->_view = new FileView($this->_resourceData);
	}

	public function getView(): iView
	{
		return $this->_view;
	}

	public function view(): void
	{
		$this->_view->view();
		Kernel::abort('root is just a dummy class!');
	}

	public static function getResourceData(int $resource_id): array
	{
		return ResourceTreeHandler::getResourceTreeEntryDataById($resource_id);
	}

	public static function getDomainContextForResource($resource_id): string
	{
		$nodes = ResourceTreeHandler::getParentNodes((int) $resource_id);

		if ($nodes === []) {
			return '';
		}

		$nodes = array_reverse($nodes);

		$node_data = ResourceTreeHandler::getResourceTreeEntryDataById($nodes[0]['node_id']);

		return $node_data['resource_name'];
	}
}
