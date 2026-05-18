<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FormResultTest extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		require_once dirname(__DIR__, 2) . '/framework/classes/class.ApiError.php';
		require_once dirname(__DIR__) . '/modules-common/Form/classes/class.FormResult.php';
	}

	public function testSuccessCarriesDomainDataWithoutTransportMetadata(): void
	{
		$result = FormResult::success(['id' => 123]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame(FormResult::OUTCOME_SUCCESS, $result->outcome());
		$this->assertSame(['id' => 123], $result->data());
		$this->assertSame([
			'outcome' => 'success',
			'data' => ['id' => 123],
		], $result->toArray());
	}

	public function testInvalidCarriesFieldErrors(): void
	{
		$result = FormResult::invalid([
			'title' => ['Required'],
			'email' => ['Invalid'],
		]);

		$this->assertTrue($result->isInvalid());
		$this->assertSame('Required', $result->firstError());
		$this->assertSame([
			'outcome' => 'invalid',
			'errors' => [
				'title' => ['Required'],
				'email' => ['Invalid'],
			],
		], $result->toArray());
	}

	public function testDeniedCarriesStructuredDomainError(): void
	{
		$error = new ApiError('FORM_DENIED', 'Denied');
		$result = FormResult::denied($error);

		$this->assertTrue($result->isDenied());
		$this->assertSame($error, $result->error());
		$this->assertSame('Denied', $result->firstError());
		$this->assertSame([
			'outcome' => 'denied',
			'error' => [
				'code' => 'FORM_DENIED',
				'message' => 'Denied',
			],
		], $result->toArray());
	}
}
