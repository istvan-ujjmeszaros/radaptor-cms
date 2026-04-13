<?php

class LayoutTypeAdminDefault extends AbstractLayoutType
{
	public const string ID = 'admin_default';

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

	/**
	 * @return array<string, string>
	 */
	public static function buildStrings(): array
	{
		return [
			'admin.menu.section.administration' => t('admin.menu.section.administration'),
		];
	}

	public function buildTree(iTreeBuildContext $webpage_composer, array $slot_trees, array $build_context = []): array
	{
		$topMenuAdmin = new LayoutComponentTopMenuAdmin($webpage_composer);
		$adminMenu = new LayoutComponentAdminMenu($webpage_composer);
		$sideMenuAdmin = new LayoutComponentSideMenuAdmin($webpage_composer);
		$userMenu = new LayoutComponentUserMenu($webpage_composer);

		return $this->createLayoutTree('layout_admin_default', [
			'lang' => substr(Kernel::getLocale(), 0, 2),
			'site_name' => Config::APP_SITE_NAME->value(),
			'administration_label' => t('admin.menu.section.administration'),
			'document_title' => t('admin.menu.section.administration') . ' - ' . Config::APP_SITE_NAME->value(),
			'saved_short_label' => t('common.saved_short'),
		], self::buildStrings(), [
			'top_menu_admin' => [$topMenuAdmin->buildTree()],
			'admin_menu' => [$adminMenu->buildTree()],
			'side_menu_admin' => [$sideMenuAdmin->buildTree()],
			'user_menu' => [$userMenu->buildTree()],
			'content' => $slot_trees['content'] ?? [],
		]);
	}
}
