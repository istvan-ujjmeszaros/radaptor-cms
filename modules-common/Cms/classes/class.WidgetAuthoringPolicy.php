<?php

declare(strict_types=1);

final class WidgetAuthoringPolicy
{
	public const string INSERT_MODE_MANUAL = 'manual';
	public const string INSERT_MODE_SYSTEM = 'system';
	public const string REUSE_REPEATABLE = 'repeatable';
	public const string REUSE_ONCE_PER_DOMAIN = 'once_per_domain';
	public const string SURFACE_PUBLIC = 'public';
	public const string SURFACE_ADMIN = 'admin';
	public const string GROUP_CONTENT = 'content';
	public const string GROUP_FORMS = 'forms';
	public const string GROUP_NAVIGATION = 'navigation';
	public const string GROUP_ADMIN = 'admin';
	public const string GROUP_DEVELOPER = 'developer';
	public const string ADMIN_ROOT_PATH = '/admin/';

	private const array DEFAULT_POLICY = [
		'insert_mode' => self::INSERT_MODE_SYSTEM,
		'reuse' => self::REUSE_REPEATABLE,
		'surfaces' => [
			self::SURFACE_PUBLIC,
			self::SURFACE_ADMIN,
		],
		'group' => self::GROUP_CONTENT,
		'sort' => 100,
	];

	/**
	 * @return array{
	 *     insert_mode: string,
	 *     reuse: string,
	 *     surfaces: list<string>,
	 *     group: string,
	 *     sort: int
	 * }
	 */
	public static function default(): array
	{
		return self::DEFAULT_POLICY;
	}

	/**
	 * @return list<string>
	 */
	public static function groupOrder(): array
	{
		return [
			self::GROUP_CONTENT,
			self::GROUP_FORMS,
			self::GROUP_NAVIGATION,
			self::GROUP_ADMIN,
			self::GROUP_DEVELOPER,
		];
	}

	/**
	 * @return array<string, string>
	 */
	public static function groupLabelKeys(): array
	{
		return [
			self::GROUP_CONTENT => 'widget.group.content',
			self::GROUP_FORMS => 'widget.group.forms',
			self::GROUP_NAVIGATION => 'widget.group.navigation',
			self::GROUP_ADMIN => 'widget.group.admin',
			self::GROUP_DEVELOPER => 'widget.group.developer',
		];
	}

	public static function surfaceForPath(string $path): string
	{
		$path = '/' . ltrim(trim($path), '/');

		if ($path === '/admin' || str_starts_with($path, self::ADMIN_ROOT_PATH)) {
			return self::SURFACE_ADMIN;
		}

		return self::SURFACE_PUBLIC;
	}

	/**
	 * @param array<string, mixed> $policy
	 */
	public static function isManual(array $policy): bool
	{
		return ($policy['insert_mode'] ?? null) === self::INSERT_MODE_MANUAL;
	}

	/**
	 * @param array<string, mixed> $policy
	 */
	public static function isOncePerDomain(array $policy): bool
	{
		return ($policy['reuse'] ?? null) === self::REUSE_ONCE_PER_DOMAIN;
	}

	/**
	 * @param array<string, mixed> $policy
	 */
	public static function supportsSurface(array $policy, string $surface): bool
	{
		return in_array($surface, is_array($policy['surfaces'] ?? null) ? $policy['surfaces'] : [], true);
	}
}
