<?php

declare(strict_types=1);

class WidgetLocaleAdmin extends AbstractWidget
{
	public const string ID = 'locale_admin';

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
		return Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER);
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/i18n/',
			'resource_name' => 'locales.html',
			'layout' => 'admin_default',
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		return $this->createComponentTree('localeAdmin', [
			'locales' => LocaleAdminService::listLocales(),
			'action_url' => Url::getUrl('locale.set-enabled', [
				'referer' => Url::getCurrentUrlForReferer(),
			]),
		]);
	}

	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER);
	}
}
