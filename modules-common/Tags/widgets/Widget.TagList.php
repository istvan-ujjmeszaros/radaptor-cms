<?php

class WidgetTagList extends AbstractWidget
{
	public const string ID = 'tag_list';
	public const bool VISIBILITY = true;

	/**
	 * @return array<string, string>
	 */
	public static function buildStrings(): array
	{
		return [
			'tags.list.title' => t('tags.list.title'),
			'tags.form.title_create' => t('tags.form.title_create'),
			'tags.field.context.label' => t('tags.field.context.label'),
			'tags.field.name.label' => t('tags.field.name.label'),
			'tags.field.description.label' => t('tags.field.description.label'),
			'common.actions' => t('common.actions'),
			'common.unknown' => t('common.unknown'),
			'common.edit' => t('common.edit'),
			'datatable.info_filtered_html' => t('datatable.info_filtered_html'),
			'datatable.info_empty' => t('datatable.info_empty'),
			'datatable.info_full' => t('datatable.info_full'),
			'datatable.empty_table' => t('datatable.empty_table'),
			'datatable.first' => t('datatable.first'),
			'datatable.last' => t('datatable.last'),
			'datatable.next' => t('datatable.next'),
			'datatable.previous' => t('datatable.previous'),
			'datatable.search' => t('datatable.search'),
			'datatable.zero_records' => t('datatable.zero_records'),
			'datatable.displayed_columns' => t('datatable.displayed_columns'),
		];
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
		return Roles::hasRole(RoleList::ROLE_SYSTEM_ADMINISTRATOR);
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/tags/',
			'resource_name' => 'index.html',
			'layout' => 'public_default',
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		$tagList = array_map(
			static function (array $tag): array {
				$tag['display_name'] = EntityTag::getDisplayNameFromValues($tag);

				return $tag;
			},
			EntityTag::getTagList()
		);

		return $this->createComponentTree('tagList', [
			'tagList' => $tagList,
		], strings: self::buildStrings());
	}
	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return true;
	}
}
