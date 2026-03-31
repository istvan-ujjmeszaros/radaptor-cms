<?php

class ResourceTypeFile extends AbstractResourceType
{
	protected FileView $_view;

	public function __construct(int $resource_id, array $resource_data)
	{
		$this->_resourceId = $resource_id;
		$this->_resourceData = $resource_data;
		$this->_view = new FileView($this->_resourceData);
	}

	public function view(): void
	{
		$this->_view->view();
	}

	public function getView(): FileView
	{
		return $this->_view;
	}

	public static function getExtradata(int $resource_id): array
	{
		$attributes = [
			'file_id',
			'title',
			'mime',
			'text',
		];

		return AttributeHandler::getAttributeArray(new AttributeResourceIdentifier(ResourceNames::RESOURCE_DATA, (string) $resource_id), $attributes);
	}

	public static function getResourceData(int $resource_id): array
	{
		return array_merge(ResourceTreeHandler::getResourceTreeEntryDataById($resource_id), ResourceTypeFile::getExtradata($resource_id));
	}

	public static function isImage(int $resource_id): bool
	{
		$extradata = ResourceTypeFile::getExtradata($resource_id);

		if (in_array($extradata['mime'], [
			'image/jpeg',
			'image/png',
			'image/gif',
		])) {
			return true;
		} else {
			return false;
		}
	}
}
