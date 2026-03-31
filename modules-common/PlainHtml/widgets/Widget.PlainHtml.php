<?php

class WidgetPlainHtml extends AbstractWidget
{
	public const string ID = 'plain_html';

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
		return Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN);
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/components/plain-html/',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		$data = PlainHtml::getSettings($connection->connection_id);

		if (!isset($data['content'])) {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => t('cms.plainhtml.not_set'),
			]);
		}

		return $this->createComponentTree('PlainHtml', [
			'data' => $data,
		]);
	}

	public function getEditableCommands(WidgetConnection $connection): array
	{
		$return = [];

		$edit = new WidgetEditCommand();

		$edit->title = t('cms.edit');
		$edit->icon = IconNames::EDIT;
		$edit->url = Form::getSeoUrl(FormList::PLAINHTML, $connection->connection_id);

		if (Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN)) {
			$return[] = $edit;
		}

		return $return;
		//return array($edit, $choose);
	}

	public static function isWrapperStylingEnabled(): bool
	{
		return false;
	}
	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return true;
	}
}
