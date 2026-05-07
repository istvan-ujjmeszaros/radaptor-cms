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
			'runtime_diagnostics.card.package_roots' => self::translate('runtime_diagnostics.card.package_roots', 'Package roots'),
			'runtime_diagnostics.card.warnings' => self::translate('runtime_diagnostics.card.warnings', 'Warnings'),
			'runtime_diagnostics.safe_to_test' => self::translate('runtime_diagnostics.safe_to_test', 'Safe to test'),
			'runtime_diagnostics.yes' => self::translate('runtime_diagnostics.yes', 'yes'),
			'runtime_diagnostics.no' => self::translate('runtime_diagnostics.no', 'no'),
			'runtime_diagnostics.none' => self::translate('runtime_diagnostics.none', 'None'),
			'runtime_diagnostics.field.environment' => self::translate('runtime_diagnostics.field.environment', 'Environment'),
			'runtime_diagnostics.field.application' => self::translate('runtime_diagnostics.field.application', 'Application'),
			'runtime_diagnostics.field.domain_context' => self::translate('runtime_diagnostics.field.domain_context', 'Domain context'),
			'runtime_diagnostics.field.runtime' => self::translate('runtime_diagnostics.field.runtime', 'Runtime'),
			'runtime_diagnostics.field.smtp_host' => self::translate('runtime_diagnostics.field.smtp_host', 'SMTP host'),
			'runtime_diagnostics.field.smtp_port' => self::translate('runtime_diagnostics.field.smtp_port', 'SMTP port'),
			'runtime_diagnostics.field.using_catcher' => self::translate('runtime_diagnostics.field.using_catcher', 'Using catcher'),
			'runtime_diagnostics.field.catcher_host' => self::translate('runtime_diagnostics.field.catcher_host', 'Catcher host'),
			'runtime_diagnostics.field.catcher_smtp_port' => self::translate('runtime_diagnostics.field.catcher_smtp_port', 'Catcher SMTP port'),
			'runtime_diagnostics.field.mailpit_ui_url' => self::translate('runtime_diagnostics.field.mailpit_ui_url', 'Mailpit UI URL'),
			'runtime_diagnostics.field.driver' => self::translate('runtime_diagnostics.field.driver', 'Driver'),
			'runtime_diagnostics.field.host' => self::translate('runtime_diagnostics.field.host', 'Host'),
			'runtime_diagnostics.field.port' => self::translate('runtime_diagnostics.field.port', 'Port'),
			'runtime_diagnostics.field.database' => self::translate('runtime_diagnostics.field.database', 'Database'),
			'runtime_diagnostics.field.username' => self::translate('runtime_diagnostics.field.username', 'Username'),
			'runtime_diagnostics.field.password' => self::translate('runtime_diagnostics.field.password', 'Password'),
			'runtime_diagnostics.field.dsn' => self::translate('runtime_diagnostics.field.dsn', 'DSN'),
			'runtime_diagnostics.field.session' => self::translate('runtime_diagnostics.field.session', 'Session'),
			'runtime_diagnostics.field.cache' => self::translate('runtime_diagnostics.field.cache', 'Cache'),
			'runtime_diagnostics.field.test' => self::translate('runtime_diagnostics.field.test', 'Test'),
			'runtime_diagnostics.field.public_url' => self::translate('runtime_diagnostics.field.public_url', 'Public URL'),
			'runtime_diagnostics.field.allowed_origins' => self::translate('runtime_diagnostics.field.allowed_origins', 'Allowed origins'),
			'runtime_diagnostics.field.enabled_hint' => self::translate('runtime_diagnostics.field.enabled_hint', 'Enabled hint'),
			'runtime_diagnostics.field.mode' => self::translate('runtime_diagnostics.field.mode', 'Mode'),
			'runtime_diagnostics.field.local_manifest' => self::translate('runtime_diagnostics.field.local_manifest', 'Local manifest'),
			'runtime_diagnostics.field.local_lock' => self::translate('runtime_diagnostics.field.local_lock', 'Local lock'),
			'runtime_diagnostics.field.workspace_dev_mode' => self::translate('runtime_diagnostics.field.workspace_dev_mode', 'Workspace dev mode'),
			'runtime_diagnostics.field.local_overrides_disabled' => self::translate('runtime_diagnostics.field.local_overrides_disabled', 'Local overrides disabled'),
			'runtime_diagnostics.col.package' => self::translate('runtime_diagnostics.col.package', 'Package'),
			'runtime_diagnostics.col.source' => self::translate('runtime_diagnostics.col.source', 'Source'),
			'runtime_diagnostics.col.version' => self::translate('runtime_diagnostics.col.version', 'Version'),
			'runtime_diagnostics.col.active_path' => self::translate('runtime_diagnostics.col.active_path', 'Active path'),
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
