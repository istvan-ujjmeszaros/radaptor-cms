<?php

declare(strict_types=1);

final class CmsTestRuntime
{
	private function __construct()
	{
	}

	public static function bootstrapFrameworkRequestRuntime(): void
	{
		$framework_root = self::frameworkRoot();

		self::requireOnce($framework_root . '/classes/class.iRequestContextStorage.php');
		self::requireOnce($framework_root . '/classes/class.DebugSessionState.php');
		self::requireOnce($framework_root . '/classes/class.RequestContext.php');
		self::requireOnce($framework_root . '/classes/class.FpmRequestContextStorage.php');
		self::requireOnce($framework_root . '/classes/class.RequestContextHolder.php');
		self::requireOnce($framework_root . '/classes/class.iSessionStorage.php');
		self::requireOnce($framework_root . '/classes/class.NativeSessionStorage.php');
		self::requireOnce($framework_root . '/classes/class.SessionContextHolder.php');
		self::requireOnce($framework_root . '/classes/class.Request.php');

		RequestContextHolder::setStorage(new FpmRequestContextStorage());

		if (!SessionContextHolder::hasStorage()) {
			SessionContextHolder::setStorage(new NativeSessionStorage());
		}
	}

	public static function bootstrapFrameworkUrlRuntime(): void
	{
		$framework_root = self::frameworkRoot();

		self::bootstrapFrameworkRequestRuntime();
		self::requireOnce($framework_root . '/interfaces/interface.iEvent.php');
		self::requireOnce($framework_root . '/classes/class.EventResolver.php');
		self::requireOnce($framework_root . '/classes/class.Url.php');
	}

	public static function bootstrapConsumerConfig(): void
	{
		if (enum_exists('Config', false)) {
			return;
		}

		$app_root = self::appRoot();

		self::requireOnce($app_root . '/config/ApplicationConfig.php');
		self::requireOnce($app_root . '/generated/__config__.php');
	}

	public static function initializeRequest(
		array $get = [],
		array $post = [],
		array $server = [],
		array $cookie = []
	): void {
		self::bootstrapFrameworkRequestRuntime();
		RequestContextHolder::initializeRequest($get, $post, $server, $cookie);
	}

	private static function frameworkRoot(): string
	{
		return dirname(__DIR__, 2) . '/framework';
	}

	private static function appRoot(): string
	{
		$candidates = [
			getenv('RADAPTOR_APP_ROOT') ?: '',
			'/app',
			dirname(__DIR__, 4) . '/radaptor-app-skeleton',
		];

		foreach ($candidates as $candidate) {
			if ($candidate !== '' && is_file($candidate . '/generated/__config__.php')) {
				return $candidate;
			}
		}

		throw new RuntimeException('Could not locate the Radaptor app root for generated Config.');
	}

	private static function requireOnce(string $path): void
	{
		if (!is_file($path)) {
			throw new RuntimeException("Required test runtime file is missing: {$path}");
		}

		require_once $path;
	}
}
