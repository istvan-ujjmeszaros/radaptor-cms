<?php

declare(strict_types=1);

final class MailpitCatcherUrls
{
	private function __construct()
	{
	}

	/**
	 * @param array<string, mixed> $query
	 */
	public static function page(int $page_id, string $subpath = '', array $query = []): string
	{
		return CatcherRouteMap::urlForPage($page_id, $subpath, $query);
	}

	/**
	 * @param array<string, mixed> $query
	 */
	public static function fragment(int $page_id, int $connection_id, string $subpath = '', array $query = []): string
	{
		$query['context'] = 'fragment';
		$query['targets'] = ['widget:' . $connection_id];

		return CatcherRouteMap::urlForPage($page_id, $subpath, $query);
	}

	/**
	 * @param array<string, mixed> $params
	 */
	public static function event(string $event, array $params = []): string
	{
		return Url::getUrl('mailpit.' . $event, $params, '&', '/');
	}
}
