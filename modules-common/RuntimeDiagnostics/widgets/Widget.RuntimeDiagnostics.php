<?php

declare(strict_types=1);

class WidgetRuntimeDiagnostics extends AbstractWidget
{
	public const string ID = 'runtime_diagnostics';

	public static function getName(): string
	{
		return self::translate('widget.' . self::ID . '.name', 'Runtime diagnostics');
	}

	public static function getDescription(): string
	{
		return self::translate('widget.' . self::ID . '.description', 'Shows redacted effective runtime state for system developers.');
	}

	public static function getListVisibility(): bool
	{
		return self::isAllowed();
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/developer/',
			'resource_name' => 'runtime-diagnostics.html',
			'layout' => 'admin_default',
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		return $this->createComponentTree(
			'runtimeDiagnostics',
			[
				'summary' => RuntimeDiagnosticsReadModel::getSummary(),
			],
			strings: self::buildStrings()
		);
	}

	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return self::isAllowed();
	}

	/**
	 * @return array<string, string>
	 */
	public static function buildStrings(): array
	{
		return [
			'runtime_diagnostics.title' => self::translate('runtime_diagnostics.title', 'Runtime diagnostics'),
			'runtime_diagnostics.subtitle' => self::translate('runtime_diagnostics.subtitle', 'Effective runtime state with secrets redacted.'),
			'runtime_diagnostics.card.environment' => self::translate('runtime_diagnostics.card.environment', 'Environment'),
			'runtime_diagnostics.card.email' => self::translate('runtime_diagnostics.card.email', 'Email safety'),
			'runtime_diagnostics.card.database' => self::translate('runtime_diagnostics.card.database', 'Database'),
			'runtime_diagnostics.card.redis' => self::translate('runtime_diagnostics.card.redis', 'Redis'),
			'runtime_diagnostics.card.mcp' => self::translate('runtime_diagnostics.card.mcp', 'MCP'),
			'runtime_diagnostics.card.packages' => self::translate('runtime_diagnostics.card.packages', 'Packages'),
			'runtime_diagnostics.card.warnings' => self::translate('runtime_diagnostics.card.warnings', 'Warnings'),
			'runtime_diagnostics.safe_to_test' => self::translate('runtime_diagnostics.safe_to_test', 'Safe to test'),
			'runtime_diagnostics.yes' => self::translate('runtime_diagnostics.yes', 'yes'),
			'runtime_diagnostics.no' => self::translate('runtime_diagnostics.no', 'no'),
			'runtime_diagnostics.none' => self::translate('runtime_diagnostics.none', 'None'),
		];
	}

	private static function isAllowed(): bool
	{
		return RuntimeDiagnosticsAccessPolicy::authorize(new PolicyContext(
			PolicyPrincipal::fromCurrentUser(),
			self::class
		))->allow;
	}

	private static function translate(string $key, string $fallback): string
	{
		$translated = t($key);

		return $translated === $key ? $fallback : $translated;
	}
}
