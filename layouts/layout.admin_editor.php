<?php

class LayoutTypeAdminEditor extends AbstractLayoutType implements iPartialNavigableLayout
{
	public const string ID = 'admin_editor';

	private static array $_SLOTS = ['content', ];

	public static function getName(): string
	{
		return t('layout.' . self::ID . '.name');
	}

	public static function getDescription(): string
	{
		return t('layout.' . self::ID . '.description');
	}

	public static function getListVisibility(): bool
	{
		return Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER);
	}

	public static function getSlots(): array
	{
		return self::$_SLOTS;
	}

	public static function getPageFragmentTargets(): array
	{
		return [
			'slot:content',
		];
	}

	public static function getFragmentLayoutComponents(): array
	{
		return [];
	}

	/**
	 * @return array<string, string>
	 */
	public static function buildStrings(): array
	{
		return [
			'admin.menu.section.administration' => t('admin.menu.section.administration'),
			'layout.admin_editor.back' => t('layout.admin_editor.back'),
		];
	}

	public function buildTree(iTreeBuildContext $webpage_composer, array $slot_trees, array $build_context = []): array
	{
		$topMenuAdmin = new LayoutComponentTopMenuAdmin($webpage_composer);
		$userMenu = new LayoutComponentUserMenu($webpage_composer);

		return $this->createLayoutTree('layout_admin_editor', [
			'lang' => Kernel::getLocale(),
			'site_name' => Config::APP_SITE_NAME->value(),
			'administration_label' => t('admin.menu.section.administration'),
			'document_title' => t('admin.menu.section.administration') . ' - ' . Config::APP_SITE_NAME->value(),
			'saved_short_label' => t('common.saved_short'),
			'back_url' => self::resolveBackUrl(
				(string)Request::_GET('return_to', ''),
				Kernel::getReferer(),
			),
			'back_label' => t('layout.admin_editor.back'),
		], self::buildStrings(), [
			'top_menu_admin' => [$topMenuAdmin->buildTree()],
			'user_menu' => [$userMenu->buildTree()],
			'content' => $slot_trees['content'] ?? [],
		]);
	}

	public static function resolveBackUrl(string $return_to = '', string $referer = '', string $fallback = '/admin/forms/'): string
	{
		foreach ([$return_to, $referer] as $candidate) {
			$safe = self::safeBackUrl($candidate);

			if ($safe !== null) {
				return $safe;
			}
		}

		return $fallback;
	}

	private static function safeBackUrl(string $url): ?string
	{
		$url = trim(Url::sanitizeRefererUrl($url));

		if ($url === '' || str_contains($url, "\n") || str_contains($url, "\r")) {
			return null;
		}

		if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
			return self::isEditorUrl($url) ? null : $url;
		}

		$parsed_url = parse_url($url);
		$current = parse_url(Url::getCurrentHost(false));

		if ($parsed_url === false || $current === false || !isset($parsed_url['host'], $current['host'])) {
			return null;
		}

		$scheme = strtolower((string)($parsed_url['scheme'] ?? ''));
		$current_scheme = strtolower((string)($current['scheme'] ?? ''));
		$host = strtolower((string)$parsed_url['host']);
		$current_host = strtolower((string)$current['host']);
		$port = (int)($parsed_url['port'] ?? self::defaultPort($scheme));
		$current_port = (int)($current['port'] ?? self::defaultPort($current_scheme));

		if ($scheme !== $current_scheme || $host !== $current_host || $port !== $current_port) {
			return null;
		}

		$path = (string)($parsed_url['path'] ?? '/');
		$query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
		$fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
		$relative_url = $path . $query . $fragment;

		return self::isEditorUrl($relative_url) ? null : $relative_url;
	}

	private static function defaultPort(string $scheme): int
	{
		return $scheme === 'https' ? 443 : 80;
	}

	private static function isEditorUrl(string $url): bool
	{
		$path = (string)(parse_url($url, PHP_URL_PATH) ?? '');

		return str_starts_with($path, '/admin/forms/edit/');
	}
}
