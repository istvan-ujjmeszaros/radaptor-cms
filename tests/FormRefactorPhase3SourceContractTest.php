<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FormRefactorPhase3SourceContractTest extends TestCase
{
	public function testFormSubmitContextOwnsReusableSessionScopedCsrfTokens(): void
	{
		$source = $this->source('modules-common/Form/classes/class.FormSubmitContext.php');

		$this->assertStringContainsString("FIELD_CSRF_TOKEN = 'csrf_token'", $source);
		$this->assertStringContainsString('SESSION_KEY_CSRF_TOKENS', $source);
		$this->assertStringContainsString('CSRF_TOKEN_TTL_SECONDS', $source);
		$this->assertStringContainsString('CSRF_TOKEN_BAG_LIMIT', $source);
		$this->assertStringContainsString('public function issueCsrfToken()', $source);
		$this->assertStringContainsString('public function validateCsrfToken(array $post): ?ApiError', $source);
		$this->assertStringContainsString('random_bytes(32)', $source);
		$this->assertStringContainsString("new ApiError(\n\t\t\t'FORM_CSRF_INVALID'", $source);
	}

	public function testEveryFormTreeEmitsCsrfHiddenFieldOutsideBusinessInputs(): void
	{
		$source = $this->source('modules-common/Form/classes/class.AbstractForm.php');

		$this->assertStringContainsString('$hidden_fields[] = $this->buildCsrfTokenTree($submit_context);', $source);
		$this->assertStringContainsString('RequestContextHolder::disablePersistentCacheWrite();', $source);
		$this->assertStringContainsString("'name' => FormSubmitContext::FIELD_CSRF_TOKEN", $source);
		$this->assertStringContainsString("'save' => false", $source);
		$this->assertStringNotContainsString("new FormInputHidden(FormSubmitContext::FIELD_CSRF_TOKEN", $source);
	}

	public function testSubmitEndpointValidatesCsrfBeforeProcessing(): void
	{
		$source = $this->source('modules-common/Form/events/Event.FormSubmit.php');

		$this->assertStringContainsString("BrowserEventDocumentationHelper::param('csrf_token'", $source);
		$this->assertStringContainsString('$csrf_error = $context->validateCsrfToken($post);', $source);
		$this->assertStringContainsString('FormResult::denied($csrf_error)', $source);
		$this->assertLessThan(
			strpos($source, '$form->process($post, $files)'),
			strpos($source, '$csrf_error = $context->validateCsrfToken($post);')
		);
	}

	private function source(string $relativePath): string
	{
		$path = dirname(__DIR__) . '/' . $relativePath;
		$this->assertFileExists($path);

		return (string) file_get_contents($path);
	}
}
