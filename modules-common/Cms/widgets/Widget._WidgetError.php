<?php

class Widget_WidgetError extends AbstractWidget
{
	public const string ID = 'widget_error';

	public function __construct(private $_type = null)
	{
	}

	public static function getName(): string
	{
		return t('widget.' . self::ID . '.name');
	}

	public static function getDescription(): string
	{
		return t('widget.' . self::ID . '.description');
	}

	public static function getListVisibility(): bool
	{
		return false;
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		// This widget is only created for the editBar() functionality.
		// The editbar is needed to delete this invalid widget from the page.
		// If a non-existent widget type is placed on a page, this is displayed.
		if (is_null($this->_type)) {
			return $this->buildStatusTree([
				'severity' => 'error',
				'message' => t('widget.widget_error.name'),
			]);
		} else {
			return $this->buildStatusTree([
				'severity' => 'error',
				'message' => t('widget.widget_error.invalid_type', ['type' => $this->_type]),
			]);
		}
	}
	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return true;
	}
}
