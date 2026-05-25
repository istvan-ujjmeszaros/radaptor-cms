<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules-common/I18n/classes/class.I18nCatalogBuilder.php';

final class I18nCatalogBuilderSortTest extends TestCase
{
	public function testCatalogExportSortsKeysWithStringOrder(): void
	{
		$method = new ReflectionMethod(I18nCatalogBuilder::class, '_sortCatalogForExport');
		$sorted = $method->invoke(null, [
			'widget.zeta.name' => 'Zeta',
			'form.alpha.name' => 'Alpha',
			'form.alpha.description' => 'Description',
		]);

		$this->assertSame([
			'form.alpha.description',
			'form.alpha.name',
			'widget.zeta.name',
		], array_keys($sorted));
	}
}
