<?php

abstract class AbstractResourceType implements iView, iResourceType
{
	protected int $_resourceId;
	protected array $_resourceData;

	public function getData(string $key): mixed
	{
		return $this->_resourceData[$key] ?? null;
	}

	public static function updateLastModified(int $resourceId, ?int $unixTimestamp = null): int
	{
		$unixTimestamp ??= time();

		DbHelper::updateHelper('resource_tree', ['last_modified' => $unixTimestamp], $resourceId);

		return $unixTimestamp;
	}

	public function getLastModified(): int
	{
		if (isset($this->_resourceData['last_modified']) && $this->_resourceData['last_modified'] != '') {
			return $this->_resourceData['last_modified'];
		} else {
			return static::updateLastModified($this->_resourceId);
		}
	}
}
