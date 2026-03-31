<?php

interface iResourceType
{
	public function getView(): iView;

	public function getData(string $key): mixed;

	public static function getResourceData(int $resource_id): array;
}
