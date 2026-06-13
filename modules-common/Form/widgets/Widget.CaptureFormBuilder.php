<?php

declare(strict_types=1);

final class WidgetCaptureFormBuilder extends AbstractWidget
{
	public const string ID = 'capture_form_builder';
	public const array AUTHORING = [
		'insert_mode' => 'system',
		'reuse' => 'repeatable',
		'surfaces' => ['admin'],
		'group' => 'forms',
		'sort' => 40,
	];

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

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/forms/',
			'resource_name' => 'index.html',
			'layout' => LayoutTypeAdminDefault::ID,
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		// The thick-client builder is retired; placements of this widget render the
		// unified form editor for the requested definition instead.
		return (new FormEditorAuthoringService())->buildEditorTree(
			(string)Request::_GET('definition_slug', ''),
		);
	}

	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN);
	}
}
