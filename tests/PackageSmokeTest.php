<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PackageSmokeTest extends TestCase
{
	public function testRegistryPackageMetadataIsValid(): void
	{
		$root = dirname(__DIR__);
		$metadata_path = $root . '/.registry-package.json';
		$this->assertFileExists($metadata_path);

		$decoded = json_decode((string) file_get_contents($metadata_path), true);
		$this->assertIsArray($decoded);
		$this->assertSame('radaptor/core/cms', $decoded['package'] ?? null);
		$this->assertSame('core', $decoded['type'] ?? null);
		$this->assertSame('cms', $decoded['id'] ?? null);
		$this->assertSame('^0.1.0', $decoded['dependencies']['radaptor/core/framework'] ?? null);
		$this->assertSame('^8.5', $decoded['composer']['require']['php'] ?? null);
	}

	public function testCmsCoreEntrypointsExist(): void
	{
		$root = dirname(__DIR__);

		$this->assertFileExists($root . '/class.LibrariesCommon.php');
		$this->assertFileExists($root . '/modules-common/Cms/classes/class.CmsResourceSpecService.php');
		$this->assertFileExists($root . '/modules-common/Cms/classes/class.CmsSeedHelper.php');
		$this->assertDirectoryExists($root . '/templates-common');
	}
}
