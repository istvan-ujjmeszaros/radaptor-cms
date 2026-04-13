<?php

class WidgetUserDescription extends AbstractWidget
{
	public const string ID = 'user_description';

	/**
	 * @return array<string, string>
	 */
	public static function buildStrings(): array
	{
		return [
			'user.description.title' => t('user.description.title'),
			'user.description.action.edit' => t('user.description.action.edit'),
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
		return Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER);
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/user/',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	public static function isCatcher(): bool
	{
		return true;
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		if (Request::_GET('id')) {
			$id = Request::_GET('id');
		} else {
			$extra_params = Url::getExtraParams($tree_build_context);

			if (isset($extra_params['standalone'][0])) {
				$id = $extra_params['standalone'][0];
			} else {
				return $this->buildStatusTree([
					'severity' => 'warning',
					'message' => t('user.error_missing_user_id'),
				]);
			}
		}

		$user_data = User::getUserFromId($id);

		if (empty($user_data)) {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => t('user.error_not_found'),
			]);
		}

		return $this->createComponentTree('userDescription', [
			'userData' => $user_data,
		], strings: self::buildStrings());
	}
	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return true;
	}
}
