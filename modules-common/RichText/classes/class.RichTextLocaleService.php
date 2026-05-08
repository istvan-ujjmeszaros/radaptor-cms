<?php

declare(strict_types=1);

final class RichTextLocaleService
{
	private static ?bool $_hasRichTextLocaleColumn = null;

	public static function getLocaleForConnectionId(int|string|null $connection_id): string
	{
		$connection_id = (int) $connection_id;

		if ($connection_id > 0) {
			$page_id = WidgetConnection::getOwnerWebpageId($connection_id);

			if ($page_id !== null && class_exists(ResourceLocaleService::class)) {
				return ResourceLocaleService::getRenderLocale((int) $page_id);
			}
		}

		return Kernel::getLocale();
	}

	public static function getLocaleForCurrentRequest(): string
	{
		return self::getLocaleForConnectionId(Request::_GET('connection_id', null));
	}

	public static function hasRichTextLocaleColumn(): bool
	{
		if (self::$_hasRichTextLocaleColumn !== null) {
			return self::$_hasRichTextLocaleColumn;
		}

		try {
			$stmt = Db::instance()->prepare(
				"SELECT 1
				FROM information_schema.COLUMNS
				WHERE TABLE_SCHEMA = DATABASE()
					AND TABLE_NAME = 'richtext'
					AND COLUMN_NAME = 'locale'"
			);
			$stmt->execute();

			return self::$_hasRichTextLocaleColumn = (bool) $stmt->fetchColumn();
		} catch (Throwable) {
			return self::$_hasRichTextLocaleColumn = false;
		}
	}
}
