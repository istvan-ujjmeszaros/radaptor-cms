<?php

declare(strict_types=1);

class WidgetCLIRunner extends AbstractWidget
{
	public const string ID = 'cli_runner';

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
			'path' => '/admin/developer/',
			'resource_name' => 'cli-runner.html',
			'layout' => 'admin_default',
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		$grouped_commands = CLICommandRegistry::getGroupedCommands();

		return $this->createComponentTree('cliRunner', [
			'commands' => $grouped_commands,
			'execute_url' => Url::getAjaxUrl('cliRunner.execute'),
		]);
	}

	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER);
	}
}
