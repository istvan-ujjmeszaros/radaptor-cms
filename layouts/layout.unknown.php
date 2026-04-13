<?php

class LayoutTypeUnknown extends AbstractLayoutType
{
	public const string ID = 'unknown';

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
		return false;
	}

	public static function getSlots(): array
	{
		return self::$_SLOTS;
	}

	public function buildTree(iTreeBuildContext $webpage_composer, array $slot_trees, array $build_context = []): array
	{
		return SduiNode::create(
			component: 'statusMessage',
			props: [
				'severity' => 'error',
				'title' => 'Unknown layout',
				'message' => 'The requested layout could not be resolved.',
			],
			type: SduiNode::TYPE_SUB,
		);
	}
}
