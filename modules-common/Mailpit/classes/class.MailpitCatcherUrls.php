<?php

declare(strict_types=1);

final class MailpitCatcherUrls
{
	/** @var null|callable(int, string, array<string, mixed>): string */
	private static mixed $pageUrlResolver = null;

	/** @var null|callable(string, array<string, mixed>): string */
	private static mixed $eventUrlResolver = null;

	private function __construct()
	{
	}

	/**
	 * @template T
	 * @param callable(int, string, array<string, mixed>): string $pageUrlResolver
	 * @param callable(string, array<string, mixed>): string $eventUrlResolver
	 * @param callable(): T $callback
	 * @return T
	 */
	public static function withResolvers(callable $pageUrlResolver, callable $eventUrlResolver, callable $callback): mixed
	{
		$previousPageUrlResolver = self::$pageUrlResolver;
		$previousEventUrlResolver = self::$eventUrlResolver;
		self::$pageUrlResolver = $pageUrlResolver;
		self::$eventUrlResolver = $eventUrlResolver;

		try {
			return $callback();
		} finally {
			self::$pageUrlResolver = $previousPageUrlResolver;
			self::$eventUrlResolver = $previousEventUrlResolver;
		}
	}

	/**
	 * @param array<string, mixed> $query
	 */
	public static function page(int $page_id, string $subpath = '', array $query = []): string
	{
		if (self::$pageUrlResolver !== null) {
			return (self::$pageUrlResolver)($page_id, $subpath, $query);
		}

		return CatcherRouteMap::urlForPage($page_id, $subpath, $query);
	}

	/**
	 * @param array<string, mixed> $query
	 */
	public static function fragment(int $page_id, int $connection_id, string $subpath = '', array $query = []): string
	{
		$query['context'] = 'fragment';
		$query['targets'] = ['widget:' . $connection_id];

		if (self::$pageUrlResolver !== null) {
			return (self::$pageUrlResolver)($page_id, $subpath, $query);
		}

		return CatcherRouteMap::urlForPage($page_id, $subpath, $query);
	}

	/**
	 * @param array<string, mixed> $params
	 */
	public static function event(string $event, array $params = []): string
	{
		if (self::$eventUrlResolver !== null) {
			return (self::$eventUrlResolver)($event, $params);
		}

		return Url::getUrl('mailpit.' . $event, $params, '&', '/');
	}
}
