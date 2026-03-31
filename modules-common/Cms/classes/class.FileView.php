<?php

class FileView implements iView, Stringable
{
	private readonly string $_resize;
	private string $_savename;

	public function __construct(private $_resource_data)
	{
		$this->_resize = ResourceTreeHandler::getResizeTreeEntryData($this->_resource_data['node_id']);

		if ($this->_resize == '') {
			$this->_savename = $this->_resource_data['resource_name'];
		} else {
			$pathinfo = pathinfo((string) $this->_resource_data['resource_name']);
			$this->_savename = $pathinfo['filename'] . '.' . $this->_resize . '.' . $pathinfo['extension'];
		}
	}

	public static function header($header): void
	{
		if (!headers_sent()) {
			header($header);
		}
	}

	public function view(): void
	{
		$attributes = AttributeHandler::getAttributes(new AttributeResourceIdentifier(ResourceNames::RESOURCE_DATA, $this->_resource_data['node_id']));

		if (Request::_GET('download', false) !== false) {
			FileContainer::forceDownload($attributes['file_id'], $this->_resize, $this->_savename);
		} else {
			FileContainer::viewInline($attributes['file_id'], $this->_resize, $this->_savename, $attributes['mime']);
		}
	}

	public function download(): void
	{
		$this->view();
	}

	public function __toString(): string
	{
		return "This is a file...";
	}
}
