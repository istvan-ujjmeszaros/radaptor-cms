<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/CmsTestRuntime.php';

CmsTestRuntime::bootstrapFrameworkUrlRuntime();
require_once dirname(__DIR__) . '/modules-common/Cms/classes/class.LocaleSwitchService.php';

final class LocaleSwitchServiceTest extends TestCase
{
	protected function setUp(): void
	{
		CmsTestRuntime::initializeRequest(server: $this->serverContext());
	}

	public function testSanitizeSameSiteReturnUrlAllowsRelativeAndSameOriginUrls(): void
	{
		$this->assertSame('/admin/?tab=content', LocaleSwitchService::sanitizeSameSiteReturnUrl('/admin/?tab=content'));
		$this->assertSame(
			'https://example.test:8443/content/page.html',
			LocaleSwitchService::sanitizeSameSiteReturnUrl('https://example.test:8443/content/page.html')
		);
	}

	public function testSanitizeSameSiteReturnUrlRejectsCrossOriginAndUnsafeUrls(): void
	{
		$this->assertSame('https://example.test:8443/', LocaleSwitchService::sanitizeSameSiteReturnUrl('https://other.test/page.html'));
		$this->assertSame('https://example.test:8443/', LocaleSwitchService::sanitizeSameSiteReturnUrl('//other.test/page.html'));
		$this->assertSame('https://example.test:8443/', LocaleSwitchService::sanitizeSameSiteReturnUrl('javascript:alert(1)'));
	}

	public function testIsSameOriginPostRequestAcceptsSameOriginOriginHeader(): void
	{
		CmsTestRuntime::initializeRequest(server: $this->serverContext([
			'REQUEST_METHOD' => 'POST',
			'HTTP_ORIGIN' => 'https://example.test:8443',
		]));

		$this->assertTrue(LocaleSwitchService::isSameOriginPostRequest());
	}

	public function testIsSameOriginPostRequestRejectsUnsafeMethodsAndOrigins(): void
	{
		CmsTestRuntime::initializeRequest(server: $this->serverContext([
			'REQUEST_METHOD' => 'GET',
			'HTTP_ORIGIN' => 'https://example.test:8443',
		]));
		$this->assertFalse(LocaleSwitchService::isSameOriginPostRequest());

		CmsTestRuntime::initializeRequest(server: $this->serverContext([
			'REQUEST_METHOD' => 'POST',
			'HTTP_ORIGIN' => 'null',
		]));
		$this->assertFalse(LocaleSwitchService::isSameOriginPostRequest());

		CmsTestRuntime::initializeRequest(server: $this->serverContext([
			'REQUEST_METHOD' => 'POST',
			'HTTP_ORIGIN' => 'https://other.test',
		]));
		$this->assertFalse(LocaleSwitchService::isSameOriginPostRequest());
	}

	public function testIsSameOriginPostRequestFallsBackToRefererWhenOriginIsAbsent(): void
	{
		CmsTestRuntime::initializeRequest(server: $this->serverContext([
			'REQUEST_METHOD' => 'POST',
			'HTTP_REFERER' => 'https://example.test:8443/admin/',
		]));

		$this->assertTrue(LocaleSwitchService::isSameOriginPostRequest());

		CmsTestRuntime::initializeRequest(server: $this->serverContext([
			'REQUEST_METHOD' => 'POST',
			'HTTP_REFERER' => 'https://other.test/admin/',
		]));
		$this->assertFalse(LocaleSwitchService::isSameOriginPostRequest());
	}

	public function testUsesRealFrameworkRequestGlobals(): void
	{
		$this->assertTrue(method_exists(Request::class, 'saveSessionData'));
		$this->assertTrue(method_exists(Request::class, '_GET'));
		$this->assertTrue(method_exists(RequestContextHolder::class, 'initializeRequest'));
		$this->assertFalse(class_exists('LocaleSwitchServiceTestRequest', false));
	}

	public function testResourceLookupPathNormalizationRejectsTraversalSegments(): void
	{
		$method = new ReflectionMethod(LocaleSwitchService::class, 'normalizeResourceLookupPath');

		$this->assertNull($method->invoke(null, '/../page.html'));
		$this->assertNull($method->invoke(null, '/foo/../bar.html'));
		$this->assertNull($method->invoke(null, '/%2e%2e/page.html'));
	}

	public function testResourceLookupPathNormalizationBuildsFolderAndResourceName(): void
	{
		$method = new ReflectionMethod(LocaleSwitchService::class, 'normalizeResourceLookupPath');

		$this->assertSame(['folder' => '/', 'resource_name' => 'index.html'], $method->invoke(null, '/'));
		$this->assertSame(['folder' => '/', 'resource_name' => 'index.html'], $method->invoke(null, '/index.html'));
		$this->assertSame(['folder' => '/folder/', 'resource_name' => 'index.html'], $method->invoke(null, '/folder/'));
		$this->assertSame(['folder' => '/folder/', 'resource_name' => 'page.html'], $method->invoke(null, '/folder/page.html'));
	}

	/**
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	private function serverContext(array $overrides = []): array
	{
		return $overrides + [
			'HTTPS' => 'on',
			'HTTP_HOST' => 'example.test',
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI' => '/',
			'SERVER_PORT' => '8443',
			'SERVER_PROTOCOL' => 'HTTP/1.1',
		];
	}
}
