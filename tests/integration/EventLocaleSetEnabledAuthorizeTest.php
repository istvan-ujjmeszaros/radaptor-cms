<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventLocaleSetEnabledAuthorizeTest extends TestCase
{
	private const string DEVELOPER_USERNAME = 'admin_developer';
	private const string NO_ROLES_USERNAME = 'user_noroles';

	private static bool $_runtime_bootstrapped = false;

	private bool $_transaction_started = false;

	protected function setUp(): void
	{
		self::bootstrapConsumerRuntime();

		if (!class_exists('Db') || !class_exists('EventLocaleSetEnabled') || !class_exists('PolicyContext') || !class_exists('RequestContextHolder')) {
			self::markTestSkipped('The Radaptor consumer app runtime is required for EventLocaleSetEnabled authorize integration tests.');
		}

		$pdo = Db::instance();

		if (!$pdo->inTransaction()) {
			$pdo->beginTransaction();
			$this->_transaction_started = true;
		}
	}

	protected function tearDown(): void
	{
		if (
			class_exists('RequestContextHolder', autoload: false)
			&& class_exists('Cache', autoload: false)
			&& class_exists('Roles', autoload: false)
			&& class_exists('User', autoload: false)
		) {
			$this->impersonate(null);
		}

		if ($this->_transaction_started && class_exists('Db', autoload: false)) {
			$pdo = Db::instance();

			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
		}

		$this->_transaction_started = false;
	}

	public function testLocaleSetEnabledIsDeniedForAnonymous(): void
	{
		$this->impersonate(null);

		$event = new EventLocaleSetEnabled();

		$this->assertFalse($event->authorize(PolicyContext::fromEvent($event))->allow);
	}

	public function testLocaleSetEnabledIsDeniedForNonDeveloper(): void
	{
		$this->ensureFixtureUserExists(self::NO_ROLES_USERNAME);
		$this->impersonate(self::NO_ROLES_USERNAME);

		$event = new EventLocaleSetEnabled();

		$this->assertFalse($event->authorize(PolicyContext::fromEvent($event))->allow);
	}

	public function testLocaleSetEnabledIsAllowedForDeveloper(): void
	{
		$this->ensureFixtureUserExists(self::DEVELOPER_USERNAME);
		$this->impersonate(self::DEVELOPER_USERNAME);

		$event = new EventLocaleSetEnabled();

		$this->assertTrue($event->authorize(PolicyContext::fromEvent($event))->allow);
	}

	private function ensureFixtureUserExists(string $username): void
	{
		$user = EntityUser::findFirst(['username' => $username]);

		if ($user === null) {
			self::markTestSkipped("Missing fixture user '{$username}'. The Radaptor app testing bootstrap must seed this user before this test can run.");
		}
	}

	private function impersonate(?string $username): void
	{
		$ctx = RequestContextHolder::current();

		if ($username === null) {
			$ctx->currentUser = null;
			$ctx->userSessionInitialized = true;
			Cache::flush(Roles::class);
			Cache::flush(User::class);

			return;
		}

		$user = EntityUser::findFirst(['username' => $username]);
		$this->assertNotNull($user, "Missing test user: {$username}");

		$ctx->currentUser = $user->data();
		$ctx->userSessionInitialized = true;
		Cache::flush(Roles::class);
		Cache::flush(User::class);
	}

	private static function bootstrapConsumerRuntime(): void
	{
		if (self::$_runtime_bootstrapped || class_exists('Db', autoload: false)) {
			self::$_runtime_bootstrapped = true;

			return;
		}

		$bootstrap = getenv('RADAPTOR_APP_TEST_BOOTSTRAP') ?: '/app/bootstrap/bootstrap.testing.php';

		if (!is_file($bootstrap)) {
			self::markTestSkipped('Set RADAPTOR_APP_TEST_BOOTSTRAP or run from the Radaptor app container to execute EventLocaleSetEnabled authorize integration tests.');
		}

		require_once $bootstrap;
		restore_error_handler();
		restore_exception_handler();

		self::$_runtime_bootstrapped = true;
	}
}
