<?php

class LayoutTypeAdminNomenu extends AbstractLayoutType
{
	public const string ID = 'admin_nomenu';

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
		return $this->createLayoutTree('layout_admin_nomenu', [
			'lang' => Kernel::getLocale(),
			'site_name' => Config::APP_SITE_NAME->value(),
			'administration_label' => t('admin.menu.section.administration'),
			'document_title' => t('admin.menu.section.administration') . ' - ' . Config::APP_SITE_NAME->value(),
		], self::buildStrings(), [
			'content' => $slot_trees['content'] ?? [],
		]);
	}
}
