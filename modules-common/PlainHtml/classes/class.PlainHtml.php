<?php

class PlainHtml
{
	public const string _RESOURCENAME = '_plain_html';

	public static function saveSettings(array $savedata, int $connection_id): int
	{
		return AttributeHandler::addAttribute(new AttributeResourceIdentifier(self::_RESOURCENAME, (string) $connection_id), $savedata);
	}

	public static function getSettings(int $connection_id): array
	{
		return AttributeHandler::getAttributes(new AttributeResourceIdentifier(self::_RESOURCENAME, (string) $connection_id));
	}
}
