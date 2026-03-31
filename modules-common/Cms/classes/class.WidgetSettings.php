<?php

class WidgetSettings
{
	public const string _RESOURCENAME = '_widget_settings';

	public static function saveSettings(array $savedata, mixed $connection_id): int
	{
		return AttributeHandler::addAttribute(new AttributeResourceIdentifier(self::_RESOURCENAME, $connection_id), $savedata);
	}

	public static function getSettings(mixed $connection_id): array
	{
		return AttributeHandler::getAttributes(new AttributeResourceIdentifier(self::_RESOURCENAME, $connection_id));
	}
}
