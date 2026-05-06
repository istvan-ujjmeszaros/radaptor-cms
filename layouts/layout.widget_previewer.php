<?php

class LayoutTypeWidgetPreviewer extends AbstractLayoutType
{
	public const string ID = 'widget_previewer';

	private static array $_SLOTS = ['content'];

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

	public function buildTree(iTreeBuildContext $webpage_composer, array $slot_trees, array $build_context = []): array
	{
		return $this->createLayoutTree('layout_widget_previewer', [
			'lang' => substr(Kernel::getLocale(), 0, 2),
			'document_title' => t('widget.widget_preview.name'),
		], contents: [
			'content' => $slot_trees['content'] ?? [],
		]);
	}
}
