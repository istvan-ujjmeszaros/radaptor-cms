<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FormRefactorPhase4CaptureIntegrationTest extends TestCase
{
	private static bool $_runtime_bootstrapped = false;
	private bool $_transaction_started = false;
	private string|false|null $_previous_app_secret = null;

	protected function setUp(): void
	{
		self::bootstrapConsumerRuntime();
		$this->_previous_app_secret = getenv('APP_SECRET');
		putenv('APP_SECRET=form-refactor-phase-4-capture-test-secret');

		foreach ([
			AbstractForm::class,
			CaptureForm::class,
			FormCaptureDefinitionRepository::class,
			FormDefinitionResolver::class,
			FormCaptureDescriptorSchemaValidator::class,
			EntityFormDefinition::class,
			EntityFormDefinitionVersion::class,
			EntityFormSubmission::class,
			FormCaptureAuthoringService::class,
			EventFormSubmit::class,
			EventFormBuilderCreate::class,
			EventFormBuilderPreviewRender::class,
			EventFormBuilderSaveDraft::class,
			EventFormBuilderPublish::class,
		] as $class_name) {
			if (!class_exists($class_name)) {
				self::markTestSkipped('The Radaptor consumer app runtime with capture-form MVP classes is required.');
			}
		}

		$pdo = Db::instance();

		if (!$pdo->inTransaction()) {
			$pdo->beginTransaction();
			$this->_transaction_started = true;
		}

		$this->setRequestContext();
	}

	protected function tearDown(): void
	{
		$this->setRequestContext();

		if ($this->_transaction_started) {
			$pdo = Db::instance();

			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
		}

		$this->_transaction_started = false;
		$this->restoreAppSecret();
	}

	public function testSystemFormsStillResolveThroughClassResolver(): void
	{
		$resolution = FormDefinitionResolver::resolve('UserLogin');

		$this->assertInstanceOf(FormDefinitionResolution::class, $resolution);
		$this->assertTrue($resolution->isSystem());
		$this->assertSame(FormTypeUserLogin::class, $resolution->className());
	}

	public function testPublishedCaptureDefinitionResolvesAndBuildsTreeWithHoneypot(): void
	{
		$resolution = $this->upsertCapture('capture-phase4-render');
		$form = $this->captureForm($resolution);
		$tree = $form->buildTree();

		$this->assertTrue($resolution->isCapture());
		$this->assertSame('capture-phase4-render', $tree['props']['form_descriptor_id']);
		$this->assertSame('Contact probe', $tree['props']['title']);
		$this->assertSame($resolution->versionId(), $tree['props']['submit_context'][FormSubmitContext::FIELD_FORM_DEFINITION_VERSION_ID]);
		$this->assertNotSame('', $tree['props']['submit_context'][FormSubmitContext::FIELD_FORM_RENDER_STATE_ID] ?? '');
		$this->assertSame('form.honeypot', $tree['slots']['hidden_fields'][1]['component'] ?? null);
		$this->assertSame('company_website', $tree['slots']['hidden_fields'][1]['props']['name'] ?? null);
	}

	public function testCaptureSchemaRejectsSystemCollisionAndUnsupportedFeatures(): void
	{
		$this->expectException(InvalidArgumentException::class);
		FormCaptureDescriptorSchemaValidator::validateForDefinition('UserLogin', $this->descriptor());
	}

	public function testCaptureSchemaRejectsDefinitionSlugLongerThanDatabaseLimit(): void
	{
		$max_length_slug = FormCaptureDescriptorSchemaValidator::CAPTURE_PREFIX . str_repeat('a', FormCaptureDescriptorSchemaValidator::MAX_DEFINITION_SLUG_LENGTH - strlen(FormCaptureDescriptorSchemaValidator::CAPTURE_PREFIX));
		FormCaptureDescriptorSchemaValidator::validateForDefinition($max_length_slug, $this->descriptor());

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('128 characters or shorter');

		FormCaptureDescriptorSchemaValidator::validateForDefinition($max_length_slug . 'a', $this->descriptor());
	}

	public function testBuilderSlugInputNormalizesToCaptureNamespace(): void
	{
		$this->assertSame('capture-asd', FormCaptureDescriptorSchemaValidator::normalizeDefinitionSlugInput('asd'));
		$this->assertSame('capture-asddsadsa', FormCaptureDescriptorSchemaValidator::normalizeDefinitionSlugInput('asddsadsa'));
		$this->assertSame('capture-asddsadsa-asddsa', FormCaptureDescriptorSchemaValidator::normalizeDefinitionSlugInput('asddsadsa-asddsa'));
		$this->assertSame('capture-asddsadsa-asddsa-asd', FormCaptureDescriptorSchemaValidator::normalizeDefinitionSlugInput('asddsadsa-asddsa-asd'));
		$this->assertSame('capture-asd-test', FormCaptureDescriptorSchemaValidator::normalizeDefinitionSlugInput(' Asd Test '));
		$this->assertSame('capture-asd-test', FormCaptureDescriptorSchemaValidator::normalizeDefinitionSlugInput('asd_test'));

		$service = new FormCaptureAuthoringService();
		$created = $service->createDefinition('plain-builder-slug', 'Plain builder slug');

		$this->assertSame('capture-plain-builder-slug', $created['definition']['definition_slug'] ?? null);
	}

	public function testCaptureSchemaRejectsAdminCodeFeatures(): void
	{
		$descriptor = $this->descriptor();
		$descriptor['fields'][0]['autocomplete_url'] = '/admin-only/provider';

		$this->expectException(InvalidArgumentException::class);
		FormCaptureDescriptorSchemaValidator::validateForDefinition('capture-phase4-invalid-feature', $descriptor);
	}

	public function testCaptureSchemaRejectsVersionedSubmitHiddenFieldsAsPayloadKeys(): void
	{
		foreach ([FormSubmitContext::FIELD_FORM_DEFINITION_VERSION_ID, FormSubmitContext::FIELD_FORM_RENDER_STATE_ID] as $reserved_key) {
			$descriptor = $this->descriptor();
			$descriptor['fields'][0]['key'] = $reserved_key;

			try {
				FormCaptureDescriptorSchemaValidator::validateForDefinition('capture-phase4-version-key-collision', $descriptor);
				$this->fail("Reserved capture payload key must be rejected: {$reserved_key}");
			} catch (InvalidArgumentException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testValidSubmitStoresPayloadByStableFieldKeys(): void
	{
		$resolution = $this->upsertCapture('capture-phase4-submit');
		$form = $this->captureForm($resolution);
		$result = $form->process([
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'name_key' => '  Ada Lovelace  ',
			'email' => 'ADA@EXAMPLE.TEST ',
			'topic' => 'support',
			'message' => '  Please contact me.  ',
			'company_website' => '',
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertSame(1, DbHelper::count('form_submissions', ['definition_id' => $resolution->definitionId()]));

		$row = DbHelper::selectOne('form_submissions', ['definition_id' => $resolution->definitionId()]);
		$this->assertIsArray($row);
		$payload = json_decode((string)$row['payload_json'], true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame([
			'name_key' => 'Ada Lovelace',
			'email' => 'ada@example.test',
			'topic' => 'support',
			'message' => 'Please contact me.',
		], $payload);
		$this->assertSame($this->expectedClientFingerprintHash('203.0.113.44', 'capture_form.ip'), $row['ip_hash']);
		$this->assertNotSame(hash('sha256', '203.0.113.44'), $row['ip_hash']);
		$this->assertSame($this->expectedClientFingerprintHash('Radaptor capture test', 'capture_form.user_agent'), $row['user_agent_hash']);
		$this->assertNotSame(hash('sha256', 'Radaptor capture test'), $row['user_agent_hash']);
	}

	public function testVersionedSubmitUsesRenderedCaptureVersionAfterLaterPublish(): void
	{
		$definition_slug = 'capture-phase4j-versioned-submit';
		$repository = new FormCaptureDefinitionRepository();
		$first = $repository->upsertPublishedDefinition($definition_slug, $this->descriptor(), $this->defaultSecurity(), 'db');
		$context = $this->submitContextForResolution($first, 'phase4j_versioned_submit');
		$post = array_merge($context->toHiddenFields(), $this->validPayload());
		$post[FormSubmitContext::FIELD_CSRF_TOKEN] = $context->issueCsrfToken();
		$changed = $repository->upsertPublishedDefinition($definition_slug, $this->changedDescriptor(), $this->defaultSecurity(), 'db');

		$this->assertNotSame($first->versionId(), $changed->versionId());

		$response = $this->runSubmitPost($post);

		$this->assertSame(200, $response['http_code']);
		$this->assertTrue($response['body']['ok']);
		$this->assertSame(1, DbHelper::count('form_submissions', ['definition_id' => $first->definitionId()]));

		$row = DbHelper::selectOne('form_submissions', ['definition_id' => $first->definitionId()]);
		$this->assertIsArray($row);
		$this->assertSame($first->versionId(), (int)$row['version_id']);
	}

	public function testVersionedSubmitRejectsTamperedCaptureVersionId(): void
	{
		$definition_slug = 'capture-phase4j-versioned-submit-tamper';
		$repository = new FormCaptureDefinitionRepository();
		$first = $repository->upsertPublishedDefinition($definition_slug, $this->descriptor(), $this->defaultSecurity(), 'db');
		$context = $this->submitContextForResolution($first, 'phase4j_versioned_submit_tamper');
		$post = array_merge($context->toHiddenFields(), $this->validPayload());
		$post[FormSubmitContext::FIELD_CSRF_TOKEN] = $context->issueCsrfToken();
		$changed = $repository->upsertPublishedDefinition($definition_slug, $this->changedDescriptor(), $this->defaultSecurity(), 'db');
		$post[FormSubmitContext::FIELD_FORM_DEFINITION_VERSION_ID] = (string)$changed->versionId();

		$response = $this->runSubmitPost($post);

		$this->assertSame(409, $response['http_code']);
		$this->assertFalse($response['body']['ok']);
		$this->assertSame('FORM_RENDER_STATE_INVALID', $response['body']['error']['code']);
		$this->assertSame('mismatch', $response['body']['error']['details']['reason']);
		$this->assertSame(0, DbHelper::count('form_submissions', ['definition_id' => $first->definitionId()]));
	}

	public function testVersionedSubmitRejectsMissingRenderStateForExplicitVersion(): void
	{
		$resolution = $this->upsertCapture('capture-phase4j-versioned-submit-missing-state');
		$context = $this->submitContextForResolution($resolution, 'phase4j_missing_render_state');
		$post = array_merge($context->toHiddenFields(), $this->validPayload());
		unset($post[FormSubmitContext::FIELD_FORM_RENDER_STATE_ID]);
		$post[FormSubmitContext::FIELD_CSRF_TOKEN] = $context->issueCsrfToken();

		$response = $this->runSubmitPost($post);

		$this->assertSame(409, $response['http_code']);
		$this->assertFalse($response['body']['ok']);
		$this->assertSame('FORM_RENDER_STATE_INVALID', $response['body']['error']['code']);
		$this->assertSame('missing', $response['body']['error']['details']['reason']);
		$this->assertSame(0, DbHelper::count('form_submissions', ['definition_id' => $resolution->definitionId()]));
	}

	public function testVersionedSubmitRejectsDroppedCaptureVersionBindingFields(): void
	{
		$resolution = $this->upsertCapture('capture-phase4j-versioned-submit-dropped-state');
		$context = $this->submitContextForResolution($resolution, 'phase4j_dropped_render_state');
		$post = array_merge($context->toHiddenFields(), $this->validPayload());
		unset(
			$post[FormSubmitContext::FIELD_FORM_DEFINITION_VERSION_ID],
			$post[FormSubmitContext::FIELD_FORM_RENDER_STATE_ID],
		);
		$post[FormSubmitContext::FIELD_CSRF_TOKEN] = $context->issueCsrfToken();

		$response = $this->runSubmitPost($post);

		$this->assertSame(409, $response['http_code']);
		$this->assertFalse($response['body']['ok']);
		$this->assertSame('FORM_RENDER_STATE_INVALID', $response['body']['error']['code']);
		$this->assertSame('missing', $response['body']['error']['details']['reason']);
		$this->assertSame(0, DbHelper::count('form_submissions', ['definition_id' => $resolution->definitionId()]));
	}

	public function testVersionedSubmitRejectsDroppedCaptureVersionBindingWithBlankBuildId(): void
	{
		$resolution = $this->upsertCapture('capture-phase4j-versioned-submit-dropped-build');
		$context = $this->submitContextForResolution($resolution, 'phase4j_dropped_build_state');
		$post = array_merge($context->toHiddenFields(), $this->validPayload());
		unset(
			$post[FormSubmitContext::FIELD_FORM_DEFINITION_VERSION_ID],
			$post[FormSubmitContext::FIELD_FORM_RENDER_STATE_ID],
		);
		$post[FormSubmitContext::FIELD_BUILD_ID] = '';
		$post[FormSubmitContext::FIELD_CSRF_TOKEN] = $context->issueCsrfToken();

		$response = $this->runSubmitPost($post);

		$this->assertSame(409, $response['http_code']);
		$this->assertFalse($response['body']['ok']);
		$this->assertSame('FORM_RENDER_STATE_INVALID', $response['body']['error']['code']);
		$this->assertSame('missing', $response['body']['error']['details']['reason']);
		$this->assertSame(0, DbHelper::count('form_submissions', ['definition_id' => $resolution->definitionId()]));
	}

	public function testVersionedSubmitRejectsDroppedCaptureVersionBindingWithTamperedContext(): void
	{
		$resolution = $this->upsertCapture('capture-phase4j-versioned-submit-dropped-context');
		$context = $this->submitContextForResolution($resolution, 'phase4j_dropped_context_state');
		$post = array_merge($context->toHiddenFields(), $this->validPayload());
		unset(
			$post[FormSubmitContext::FIELD_FORM_DEFINITION_VERSION_ID],
			$post[FormSubmitContext::FIELD_FORM_RENDER_STATE_ID],
		);
		$post[FormSubmitContext::FIELD_FORM_INSTANCE_ID] = 'tampered_instance';
		$post[FormSubmitContext::FIELD_ITEM_ID] = '999';
		$post[FormSubmitContext::FIELD_HOST_PAGE_ID] = '';
		$post[FormSubmitContext::FIELD_WIDGET_CONNECTION_ID] = '';
		$post[FormSubmitContext::FIELD_CSRF_TOKEN] = $context->issueCsrfToken();

		$response = $this->runSubmitPost($post);

		$this->assertSame(409, $response['http_code']);
		$this->assertFalse($response['body']['ok']);
		$this->assertSame('FORM_RENDER_STATE_INVALID', $response['body']['error']['code']);
		$this->assertSame('missing', $response['body']['error']['details']['reason']);
		$this->assertSame(0, DbHelper::count('form_submissions', ['definition_id' => $resolution->definitionId()]));
	}

	public function testMissingVersionedSubmitFallsBackToCurrentPublishedCaptureVersion(): void
	{
		$definition_slug = 'capture-phase4j-versioned-submit-fallback';
		$repository = new FormCaptureDefinitionRepository();
		$first = $repository->upsertPublishedDefinition($definition_slug, $this->descriptor(), $this->defaultSecurity(), 'db');
		$changed = $repository->upsertPublishedDefinition($definition_slug, $this->changedDescriptor(), $this->defaultSecurity(), 'db');
		$context = new FormSubmitContext(
			formId: $definition_slug,
			formInstanceId: 'phase4j_missing_version',
			itemId: null,
			returnTarget: '/capture-return',
			hostPageId: null,
			widgetConnectionId: null,
			buildId: FormSubmitContext::currentBuildId(),
		);

		$this->assertNotSame($first->versionId(), $changed->versionId());

		$response = $this->runSubmitEvent($context, $this->validPayload());

		$this->assertSame(200, $response['http_code']);
		$row = DbHelper::selectOne('form_submissions', ['definition_id' => $changed->definitionId()]);
		$this->assertIsArray($row);
		$this->assertSame($changed->versionId(), (int)$row['version_id']);
	}

	public function testMissingAppSecretFailsAsControlledCaptureRuntimeError(): void
	{
		$resolution = $this->upsertCapture('capture-phase4-missing-secret');
		putenv('APP_SECRET');

		try {
			$this->captureForm($resolution)->process($this->validPayload());
			$this->fail('Capture submission without APP_SECRET should fail as a controlled runtime exception.');
		} catch (FormCaptureRuntimeException $exception) {
			$this->assertSame('FORM_CAPTURE_SECRET_MISSING', $exception->apiCode());
			$this->assertSame('form.capture.error_unavailable', $exception->messageKey());
			$this->assertSame(500, $exception->httpStatus());
			$this->assertSame(0, DbHelper::count('form_submissions', ['definition_id' => $resolution->definitionId()]));
		} finally {
			putenv('APP_SECRET=form-refactor-phase-4-capture-test-secret');
		}
	}

	public function testPlaceholderAppSecretFailsAsControlledCaptureRuntimeError(): void
	{
		$resolution = $this->upsertCapture('capture-phase4-placeholder-secret');
		putenv('APP_SECRET=change-me-to-a-random-secret');

		try {
			$this->captureForm($resolution)->process($this->validPayload());
			$this->fail('Capture submission with placeholder APP_SECRET should fail as a controlled runtime exception.');
		} catch (FormCaptureRuntimeException $exception) {
			$this->assertSame('FORM_CAPTURE_SECRET_MISSING', $exception->apiCode());
			$this->assertSame('form.capture.error_unavailable', $exception->messageKey());
			$this->assertSame(500, $exception->httpStatus());
			$this->assertSame(0, DbHelper::count('form_submissions', ['definition_id' => $resolution->definitionId()]));
		} finally {
			putenv('APP_SECRET=form-refactor-phase-4-capture-test-secret');
		}
	}

	public function testInvalidHoneypotAndRateLimitRejectWithoutStorage(): void
	{
		$resolution = $this->upsertCapture('capture-phase4-security', [
			'rate_limit' => [
				'accepted' => 1,
				'window_seconds' => 600,
			],
		]);

		$invalid = $this->captureForm($resolution)->process([
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'name_key' => '',
			'email' => 'not-email',
			'topic' => 'general',
			'message' => 'short',
			'company_website' => '',
		]);
		$this->assertTrue($invalid->isInvalid());
		$this->assertSame(0, DbHelper::count('form_submissions', ['definition_id' => $resolution->definitionId()]));

		$honeypot = $this->captureForm($resolution)->process([
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'name_key' => 'Ada Lovelace',
			'email' => 'ada@example.test',
			'topic' => 'general',
			'message' => 'This message is long enough.',
			'company_website' => 'https://bot.example.test',
		]);
		$this->assertTrue($honeypot->isDenied());
		$this->assertSame(0, DbHelper::count('form_submissions', ['definition_id' => $resolution->definitionId()]));

		$first = $this->captureForm($resolution)->process($this->validPayload());
		$this->assertTrue($first->isSuccess());
		$this->assertSame(1, DbHelper::count('form_submissions', ['definition_id' => $resolution->definitionId()]));

		$limited = $this->captureForm($resolution)->process($this->validPayload());
		$this->assertTrue($limited->isDenied());
		$this->assertSame(1, DbHelper::count('form_submissions', ['definition_id' => $resolution->definitionId()]));
	}

	public function testDraftDisabledMissingAndInvalidCaptureDefinitionsAreControlledRejections(): void
	{
		$security_json = json_encode(
			FormCaptureDescriptorSchemaValidator::normalizeSecurity(null, ['name_key', 'email', 'topic', 'message']),
			JSON_THROW_ON_ERROR,
		);
		EntityFormDefinition::createFromArray([
			'definition_slug' => 'capture-phase4-draft',
			'kind' => 'capture',
			'source' => 'db',
			'status' => 'draft',
			'security_json' => $security_json,
		]);
		$this->assertNull(FormDefinitionResolver::resolve('capture-phase4-draft'));

		$disabled = (new FormCaptureDefinitionRepository())->upsertPublishedDefinition('capture-phase4-disabled', $this->descriptor());
		EntityFormDefinition::updateById($disabled->definitionId(), ['status' => 'disabled']);
		$this->assertNull(FormDefinitionResolver::resolve('capture-phase4-disabled'));

		$this->assertNull(FormDefinitionResolver::resolve('capture-phase4-missing'));

		$invalid_definition = EntityFormDefinition::createFromArray([
			'definition_slug' => 'capture-phase4-invalid-runtime',
			'kind' => 'capture',
			'source' => 'db',
			'status' => 'published',
			'security_json' => $security_json,
		]);
		$invalid_version = EntityFormDefinitionVersion::createFromArray([
			'definition_id' => (int)$invalid_definition->definition_id,
			'version_number' => 1,
			'status' => 'published',
			'descriptor_json' => '{"kind":"capture","fields":[{"type":"hidden","name":"secret"}]}',
			'descriptor_hash' => hash('sha256', 'invalid-runtime'),
			'published_at' => date('Y-m-d H:i:s'),
		]);
		EntityFormDefinition::updateById((int)$invalid_definition->definition_id, [
			'published_version_id' => (int)$invalid_version->version_id,
		]);

		try {
			FormDefinitionResolver::resolve('capture-phase4-invalid-runtime');
			$this->fail('Invalid runtime capture descriptor should be reported as a controlled runtime exception.');
		} catch (FormCaptureRuntimeException $exception) {
			$this->assertSame('FORM_CAPTURE_DESCRIPTOR_INVALID', $exception->apiCode());
			$this->assertSame(500, $exception->httpStatus());
		}
	}

	public function testPhase4fPublishDryRunDoesNotWriteLiveStateAndApplyPublishesWhenPublisherExists(): void
	{
		if (!class_exists('FormCaptureDescriptorSpecLoader')) {
			self::markTestSkipped('Phase 4f capture descriptor spec loader is not implemented yet.');
		}

		$definition_slug = 'capture-phase4f-publish';
		$security = $this->defaultSecurity();

		$dry_run = FormCaptureDescriptorSpecLoader::previewPublish($definition_slug, $this->descriptor(), $security, 'db');

		$this->assertNull(EntityFormDefinition::findBySlug($definition_slug));
		$this->assertSame('success', $dry_run['status'] ?? null);
		$this->assertTrue($dry_run['dry_run'] ?? false);
		$this->assertSame('validated', $dry_run['definitions'][0]['status'] ?? null);
		$this->assertSame(1, $dry_run['summary']['would_publish'] ?? null);
		$this->assertSame(0, $dry_run['summary']['published'] ?? null);

		$published = FormCaptureDescriptorSpecLoader::applyPublish($definition_slug, $this->descriptor(), $security, 'db');
		$resolution = FormDefinitionResolver::resolve($definition_slug);

		$this->assertSame('success', $published['status'] ?? null);
		$this->assertFalse($published['dry_run'] ?? true);
		$this->assertInstanceOf(FormDefinitionResolution::class, $resolution);
		$this->assertSame($definition_slug, $resolution->definitionSlug());
		$this->assertSame(1, (int)($resolution->version()['version_number'] ?? 0));
		$this->assertNotNull(EntityFormDefinition::findBySlug($definition_slug));
	}

	public function testInvalidDescriptorIsRejectedBeforeLiveDefinitionStateChanges(): void
	{
		$definition_slug = 'capture-phase4f-invalid-before-live-state';
		$descriptor = $this->descriptor();
		$descriptor['fields'][0]['autocomplete_url'] = '/admin-only/provider';

		try {
			(new FormCaptureDefinitionRepository())->upsertPublishedDefinition($definition_slug, $descriptor, $this->defaultSecurity());
			$this->fail('Invalid descriptors must be rejected before form_definitions or versions are written.');
		} catch (InvalidArgumentException) {
			$this->assertNull(EntityFormDefinition::findBySlug($definition_slug));
			$this->assertSame(0, DbHelper::count('form_definition_versions', ['definition_id' => -1]));
		}
	}

	public function testIdenticalPublishReusesVersionAndChangedDescriptorBumpsVersion(): void
	{
		$definition_slug = 'capture-phase4f-version-reuse';
		$repository = new FormCaptureDefinitionRepository();

		$first = $repository->upsertPublishedDefinition($definition_slug, $this->descriptor(), $this->defaultSecurity());
		$second = $repository->upsertPublishedDefinition($definition_slug, $this->descriptor(), $this->defaultSecurity());
		$changed = $repository->upsertPublishedDefinition($definition_slug, $this->changedDescriptor(), $this->defaultSecurity());

		$this->assertSame($first->versionId(), $second->versionId());
		$this->assertSame(1, (int)($first->version()['version_number'] ?? 0));
		$this->assertNotSame($first->versionId(), $changed->versionId());
		$this->assertSame(2, (int)($changed->version()['version_number'] ?? 0));

		$definition = EntityFormDefinition::findBySlug($definition_slug);
		$this->assertNotNull($definition);
		$this->assertSame($changed->versionId(), (int)$definition->published_version_id);
	}

	public function testDbDescriptorHashMismatchIsRejectedBeforeRuntimeResolution(): void
	{
		$resolution = $this->upsertCapture('capture-phase4f-hash-mismatch');
		EntityFormDefinitionVersion::updateById($resolution->versionId(), [
			'descriptor_hash' => str_repeat('0', 64),
		]);

		try {
			FormDefinitionResolver::resolve('capture-phase4f-hash-mismatch');
			$this->fail('Published descriptors whose stored hash does not match descriptor_json must be rejected.');
		} catch (FormCaptureRuntimeException $exception) {
			$this->assertSame('FORM_CAPTURE_DESCRIPTOR_INVALID', $exception->apiCode());
			$this->assertSame(500, $exception->httpStatus());
		}
	}

	public function testRuntimeCacheInvalidatesAfterVersionBumpWhenCacheExists(): void
	{
		if (!class_exists('FormCaptureCompiledDescriptorCache')) {
			self::markTestSkipped('Phase 4f compiled descriptor cache is not implemented yet.');
		}

		$definition_slug = 'capture-phase4f-cache-bump';
		$cache = new FormCaptureCompiledDescriptorCache();
		$repository = new FormCaptureDefinitionRepository();
		$first = $repository->upsertPublishedDefinition($definition_slug, $this->descriptor(), $this->defaultSecurity());
		$cache->write($first->definition(), $first->version(), $first->descriptor(), $first->security());

		$changed = $repository->upsertPublishedDefinition($definition_slug, $this->changedDescriptor(), $this->defaultSecurity());
		$cache->deleteStaleForSlug($definition_slug, (int)($changed->version()['version_number'] ?? 0));
		$stale_entry = $cache->read($first->definition(), $first->version());
		$resolved = FormDefinitionResolver::resolve($definition_slug);

		$this->assertNull($stale_entry);
		$this->assertInstanceOf(FormDefinitionResolution::class, $resolved);
		$this->assertSame($changed->versionId(), $resolved->versionId());
		$this->assertSame('Contact probe changed', $resolved->descriptor()['title']['text'] ?? null);
	}

	public function testCorruptTruncatedAndStaleRuntimeCacheFallsBackToDatabaseWhenCacheExists(): void
	{
		if (!class_exists('FormCaptureCompiledDescriptorCache')) {
			self::markTestSkipped('Phase 4f compiled descriptor cache is not implemented yet.');
		}

		$definition_slug = 'capture-phase4f-cache-fallback';
		$repository = new FormCaptureDefinitionRepository();
		$published = $repository->upsertPublishedDefinition($definition_slug, $this->descriptor(), $this->defaultSecurity());
		$cache = new FormCaptureCompiledDescriptorCache();
		$write = $cache->write($published->definition(), $published->version(), $published->descriptor(), $published->security());
		$path = (string)($write['path'] ?? '');
		$this->assertFileExists($path);

		foreach (['<?php return [;', "<?php\n\nreturn ['version_id' => 0];\n", "<?php\n\nreturn ['descriptor_hash' => '" . str_repeat('f', 64) . "'];\n"] as $payload) {
			file_put_contents($path, $payload);
			$this->assertNull($cache->read($published->definition(), $published->version()));
			$resolved = FormDefinitionResolver::resolve($definition_slug);

			$this->assertInstanceOf(FormDefinitionResolution::class, $resolved);
			$this->assertSame($published->versionId(), $resolved->versionId());
		}

		$tampered = $cache->write($published->definition(), $published->version(), $published->descriptor(), $published->security());
		$tampered_entry = $tampered['entry'];
		$tampered_entry['descriptor']['title']['text'] = 'Tampered cache title';
		$tampered_entry['normalized_descriptor_hash'] = FormCaptureCompiledDescriptorCache::hashData($tampered_entry['descriptor']);
		file_put_contents($path, "<?php\n\nreturn " . var_export($tampered_entry, true) . ";\n");

		$resolved = FormDefinitionResolver::resolve($definition_slug);

		$this->assertInstanceOf(FormDefinitionResolution::class, $resolved);
		$this->assertSame($published->versionId(), $resolved->versionId());
		$this->assertSame($published->descriptor()['title']['text'] ?? null, $resolved->descriptor()['title']['text'] ?? null);
	}

	public function testCompiledDescriptorCacheGcKeepsCurrentAndDeletesStaleOnlyOnApply(): void
	{
		if (!class_exists('FormCaptureCompiledDescriptorCacheGarbageCollector')) {
			self::markTestSkipped('Form cache GC is not implemented yet.');
		}

		$definition_slug = 'capture-phase4f-cache-gc-stale';
		$repository = new FormCaptureDefinitionRepository();
		$cache = new FormCaptureCompiledDescriptorCache();
		$gc = new FormCaptureCompiledDescriptorCacheGarbageCollector($cache);
		$first = $repository->upsertPublishedDefinition($definition_slug, $this->descriptor(), $this->defaultSecurity());
		$changed = $repository->upsertPublishedDefinition($definition_slug, $this->changedDescriptor(), $this->defaultSecurity());
		$stale_write = $cache->write($first->definition(), $first->version(), $first->descriptor(), $first->security());
		$current_write = $cache->write($changed->definition(), $changed->version(), $changed->descriptor(), $changed->security());
		$stale_path = (string)$stale_write['path'];
		$current_path = (string)$current_write['path'];

		$dry_run = $gc->run(true, $definition_slug);

		$this->assertSame('success', $dry_run['status']);
		$this->assertSame(2, $dry_run['matched_files']);
		$this->assertSame(1, $dry_run['kept_files']);
		$this->assertSame(1, $dry_run['delete_candidates']);
		$this->assertSame(0, $dry_run['deleted_files']);
		$this->assertFileExists($stale_path);
		$this->assertFileExists($current_path);

		$applied = $gc->run(false, $definition_slug);

		$this->assertSame('success', $applied['status']);
		$this->assertSame(1, $applied['deleted_files']);
		$this->assertFileDoesNotExist($stale_path);
		$this->assertFileExists($current_path);
	}

	public function testCompiledDescriptorCacheGcDeletesCorruptCurrentCacheAndRuntimeRebuilds(): void
	{
		if (!class_exists('FormCaptureCompiledDescriptorCacheGarbageCollector')) {
			self::markTestSkipped('Form cache GC is not implemented yet.');
		}

		$definition_slug = 'capture-phase4f-cache-gc-corrupt';
		$published = (new FormCaptureDefinitionRepository())->upsertPublishedDefinition($definition_slug, $this->descriptor(), $this->defaultSecurity());
		$cache = new FormCaptureCompiledDescriptorCache();
		$write = $cache->write($published->definition(), $published->version(), $published->descriptor(), $published->security());
		$path = (string)$write['path'];
		file_put_contents($path, '<?php return [;');

		$applied = (new FormCaptureCompiledDescriptorCacheGarbageCollector($cache))->run(false, $definition_slug);

		$this->assertSame('success', $applied['status']);
		$this->assertSame(1, $applied['delete_candidates']);
		$this->assertSame(1, $applied['deleted_files']);
		$this->assertFileDoesNotExist($path);

		$resolved = FormDefinitionResolver::resolve($definition_slug);

		$this->assertInstanceOf(FormDefinitionResolution::class, $resolved);
		$this->assertSame($published->versionId(), $resolved->versionId());
		$this->assertFileExists($path);
	}

	public function testCompiledDescriptorCacheGcRejectsUnsafeSlugBeforeGlob(): void
	{
		if (!class_exists('FormCaptureCompiledDescriptorCacheGarbageCollector')) {
			self::markTestSkipped('Form cache GC is not implemented yet.');
		}

		$this->expectException(InvalidArgumentException::class);

		(new FormCaptureCompiledDescriptorCacheGarbageCollector())->run(true, '../*');
	}

	public function testRuntimeResolutionStillSucceedsWhenCacheRewriteFails(): void
	{
		if (!class_exists('FormCaptureCompiledDescriptorCache')) {
			self::markTestSkipped('Phase 4f compiled descriptor cache is not implemented yet.');
		}

		$definition_slug = 'capture-phase4f-cache-write-failure';
		$published = (new FormCaptureDefinitionRepository())->upsertPublishedDefinition($definition_slug, $this->descriptor(), $this->defaultSecurity());
		$cache = new FormCaptureCompiledDescriptorCache();
		$write = $cache->write($published->definition(), $published->version(), $published->descriptor(), $published->security());
		$path = (string)($write['path'] ?? '');
		$this->assertFileExists($path);
		@unlink($path);
		$this->assertTrue(mkdir($path));

		try {
			$resolved = FormDefinitionResolver::resolve($definition_slug);
		} finally {
			@rmdir($path);
		}

		$this->assertInstanceOf(FormDefinitionResolution::class, $resolved);
		$this->assertSame($published->versionId(), $resolved->versionId());
	}

	public function testLegacyRawDescriptorHashCanStillValidateCompiledCache(): void
	{
		if (!class_exists('FormCaptureCompiledDescriptorCache')) {
			self::markTestSkipped('Phase 4f compiled descriptor cache is not implemented yet.');
		}

		$definition_slug = 'capture-phase4f-legacy-raw-hash';
		$raw_descriptor = $this->descriptor();
		$normalized_descriptor = FormCaptureDescriptorSchemaValidator::normalizeDescriptor($raw_descriptor);
		$raw_descriptor_json = FormCaptureCompiledDescriptorCache::encodeJson($raw_descriptor);
		$raw_descriptor_hash = hash('sha256', $raw_descriptor_json);
		$normalized_descriptor_hash = FormCaptureCompiledDescriptorCache::hashData($normalized_descriptor);
		$this->assertNotSame($raw_descriptor_hash, $normalized_descriptor_hash);

		$published = (new FormCaptureDefinitionRepository())->upsertPublishedDefinition($definition_slug, $raw_descriptor, $this->defaultSecurity());
		EntityFormDefinitionVersion::updateById($published->versionId(), [
			'descriptor_json' => $raw_descriptor_json,
			'descriptor_hash' => $raw_descriptor_hash,
		]);
		$version = EntityFormDefinitionVersion::findFirst(['version_id' => $published->versionId()]);
		$this->assertNotNull($version);

		$cache = new FormCaptureCompiledDescriptorCache();
		$cache->write($published->definition(), $version->dto(), $normalized_descriptor, $published->security());
		$resolved = FormDefinitionResolver::resolve($definition_slug);
		$cached = $cache->read($published->definition(), $version->dto());

		$this->assertInstanceOf(FormDefinitionResolution::class, $resolved);
		$this->assertSame($published->versionId(), $resolved->versionId());
		$this->assertIsArray($cached);
		$this->assertSame($raw_descriptor_hash, $cached['descriptor_hash'] ?? null);
		$this->assertSame($normalized_descriptor_hash, $cached['normalized_descriptor_hash'] ?? null);
	}

	public function testCaptureWidgetUnavailableDefinitionRendersFallbackStatus(): void
	{
		if (!class_exists('WidgetCaptureForm')) {
			self::markTestSkipped('Capture form widget is not available.');
		}

		$widget = new WidgetCaptureForm();
		$connection = new WidgetConnection([
			'connection_id' => 4401,
			'widget_name' => 'CaptureForm',
			'slot_name' => 'content',
			'extraparams' => [
				'definition_slug' => 'capture-phase4f-missing-widget-definition',
			],
		]);

		$tree = $widget->buildTree($this->treeContext(), $connection);

		$this->assertSame('statusMessage', $tree['component']);
		$this->assertSame('warning', $tree['props']['severity'] ?? null);
		$this->assertSame(t('form.capture.error_unavailable'), $tree['props']['message'] ?? null);
	}

	public function testBuilderPreviewUsesStoredDefinitionSecurity(): void
	{
		$definition_slug = 'capture-phase4j-builder-preview-security';
		$security = array_replace_recursive($this->defaultSecurity(), [
			'honeypot' => [
				'field_name' => 'preview_security_probe',
			],
		]);
		$published = (new FormCaptureDefinitionRepository())->upsertPublishedDefinition($definition_slug, $this->descriptor(), $security, 'db');
		$preview = (new FormCaptureAuthoringService())->renderPreview($definition_slug, $published->descriptor());

		$this->assertStringContainsString('name="preview_security_probe"', (string)($preview['html'] ?? ''));
		$this->assertStringNotContainsString('name="company_website"', (string)($preview['html'] ?? ''));
	}

	public function testBuilderPreviewDoesNotIssueCaptureRenderState(): void
	{
		$definition_slug = 'capture-phase4j-builder-preview-render-state';
		$published = (new FormCaptureDefinitionRepository())->upsertPublishedDefinition($definition_slug, $this->descriptor(), $this->defaultSecurity(), 'db');
		Request::saveSessionData([FormSubmitContext::SESSION_KEY_RENDER_STATES], []);

		$preview = (new FormCaptureAuthoringService())->renderPreview($definition_slug, $published->descriptor());

		$this->assertIsString($preview['html'] ?? null);
		$this->assertSame([], Request::_SESSION(FormSubmitContext::SESSION_KEY_RENDER_STATES, []));
	}

	public function testBuilderAuthoringCreatesSavesOneActiveDraftAndPublishes(): void
	{
		$definition_slug = 'capture-phase4j-builder-lifecycle';
		$service = new FormCaptureAuthoringService();
		$created = $service->createDefinition($definition_slug, 'Builder lifecycle');
		$saved = $service->saveDraft($definition_slug, $this->descriptorWithTitle('Builder draft'), (string)$created['base_server_hash']);
		$draft_version_id = (int)($saved['active_draft']['version_id'] ?? 0);

		$this->assertSame('created', $created['action'] ?? null);
		$this->assertSame('saved_draft', $saved['action'] ?? null);
		$this->assertGreaterThan(0, $draft_version_id);
		$this->assertSame(1, DbHelper::count('form_definition_versions', [
			'definition_id' => (int)($saved['definition']['definition_id'] ?? 0),
			'status' => 'draft',
		]));
		$this->assertSame(1, DbHelper::count('form_definition_versions', [
			'definition_id' => (int)($saved['definition']['definition_id'] ?? 0),
			'status' => 'abandoned',
		]));

		$published = $service->publishDraft($definition_slug, $draft_version_id);
		$resolved = FormDefinitionResolver::resolve($definition_slug);

		$this->assertSame('published', $published['action'] ?? null);
		$this->assertSame($draft_version_id, (int)($published['published_version_id'] ?? 0));
		$this->assertNull($published['active_draft'] ?? null);
		$this->assertInstanceOf(FormDefinitionResolution::class, $resolved);
		$this->assertSame($draft_version_id, $resolved->versionId());
		$this->assertSame(0, DbHelper::count('form_definition_versions', [
			'definition_id' => (int)($published['definition']['definition_id'] ?? 0),
			'status' => 'draft',
		]));
	}

	public function testBuilderAuthoringRejectsAbandonedDraftPublishByExplicitVersion(): void
	{
		$definition_slug = 'capture-phase4j-builder-stale-draft';
		$service = new FormCaptureAuthoringService();
		$created = $service->createDefinition($definition_slug, 'Builder stale draft');
		$first = $service->saveDraft($definition_slug, $this->descriptorWithTitle('First draft'), (string)$created['base_server_hash']);
		$first_draft_id = (int)($first['active_draft']['version_id'] ?? 0);
		$second = $service->saveDraft($definition_slug, $this->descriptorWithTitle('Second draft'), (string)$first['base_server_hash']);
		$second_draft_id = (int)($second['active_draft']['version_id'] ?? 0);

		try {
			$service->publishDraft($definition_slug, $first_draft_id);
			$this->fail('Explicit publish must reject abandoned draft versions.');
		} catch (InvalidArgumentException $exception) {
			$this->assertSame('Only the active draft version can be published.', $exception->getMessage());
		}

		$definition = EntityFormDefinition::findBySlug($definition_slug);
		$first_version = EntityFormDefinitionVersion::findFirst(['version_id' => $first_draft_id]);
		$second_version = EntityFormDefinitionVersion::findFirst(['version_id' => $second_draft_id]);

		$this->assertInstanceOf(EntityFormDefinition::class, $definition);
		$this->assertNull($definition->published_version_id);
		$this->assertSame('abandoned', (string)$first_version?->status);
		$this->assertSame('draft', (string)$second_version?->status);
	}

	public function testBuilderStateIncludesVersionHistoryForReadOnlySelectionUi(): void
	{
		$definition_slug = 'capture-phase4j-builder-version-history';
		$service = new FormCaptureAuthoringService();
		$created = $service->createDefinition($definition_slug, 'Builder version history');
		$saved = $service->saveDraft($definition_slug, $this->descriptorWithTitle('Builder version history draft'), (string)$created['base_server_hash']);
		$service->publishDraft($definition_slug, (int)($saved['active_draft']['version_id'] ?? 0));
		$state = $service->loadDefinition($definition_slug);

		$this->assertIsArray($state['versions'] ?? null);
		$this->assertGreaterThanOrEqual(2, count($state['versions']));
		$this->assertSame([2, 1], array_column($state['versions'], 'version_number'));
	}

	public function testAbandonedDraftGcPrunesOnlyOldUnreferencedAbandonedDrafts(): void
	{
		$definition_slug = 'capture-phase4j-builder-draft-gc';
		$service = new FormCaptureAuthoringService();
		$created = $service->createDefinition($definition_slug, 'Builder draft GC');
		$first = $service->saveDraft($definition_slug, $this->descriptorWithTitle('Draft GC first'), (string)$created['base_server_hash']);
		$first_draft_id = (int)($first['active_draft']['version_id'] ?? 0);
		$second = $service->saveDraft($definition_slug, $this->descriptorWithTitle('Draft GC second'), (string)$first['base_server_hash']);
		$second_draft_id = (int)($second['active_draft']['version_id'] ?? 0);
		EntityFormDefinitionVersion::updateById($first_draft_id, [
			'created_at' => date('Y-m-d H:i:s', time() - 172800),
		]);

		$gc = new FormCaptureDraftGarbageCollector();
		$dry_run = $gc->run(1, true);

		$this->assertGreaterThanOrEqual(1, $dry_run['matched_rows']);
		$this->assertSame(0, $dry_run['deleted_rows']);
		$this->assertInstanceOf(EntityFormDefinitionVersion::class, EntityFormDefinitionVersion::findFirst(['version_id' => $first_draft_id]));

		$applied = $gc->run(1, false);

		$this->assertGreaterThanOrEqual(1, $applied['deleted_rows']);
		$this->assertNull(EntityFormDefinitionVersion::findFirst(['version_id' => $first_draft_id]));
		$this->assertInstanceOf(EntityFormDefinitionVersion::class, EntityFormDefinitionVersion::findFirst(['version_id' => $second_draft_id]));
	}

	public function testBuilderAuthoringRejectsShippedDefinitionsAndReportsBaseHashConflict(): void
	{
		$service = new FormCaptureAuthoringService();
		$shipped_slug = 'capture-phase4j-builder-shipped';
		(new FormCaptureDefinitionRepository())->upsertPublishedDefinition($shipped_slug, $this->descriptor(), $this->defaultSecurity(), 'shipped');

		try {
			$service->saveDraft($shipped_slug, $this->descriptorWithTitle('Shipped edit'), '');
			$this->fail('Shipped capture definitions must stay read-only in the builder.');
		} catch (InvalidArgumentException $exception) {
			$this->assertStringContainsString('read-only', $exception->getMessage());
		}

		$conflict_slug = 'capture-phase4j-builder-conflict';
		$created = $service->createDefinition($conflict_slug, 'Builder conflict');
		$first = $service->saveDraft($conflict_slug, $this->descriptorWithTitle('Server draft'), (string)$created['base_server_hash']);
		$conflict = $service->saveDraft($conflict_slug, $this->descriptorWithTitle('Stale local draft'), (string)$created['base_server_hash']);
		$overwrite = $service->saveDraft($conflict_slug, $this->descriptorWithTitle('Overwrite local draft'), (string)$created['base_server_hash'], true);

		$this->assertSame('saved_draft', $first['action'] ?? null);
		$this->assertSame('conflict', $conflict['status'] ?? null);
		$this->assertSame((int)($first['active_draft']['version_id'] ?? 0), (int)($conflict['server']['active_draft']['version_id'] ?? 0));
		$this->assertSame('saved_draft', $overwrite['action'] ?? null);
	}

	public function testBuilderCreateEventRejectsMissingCsrfBeforeMutation(): void
	{
		$definition_slug = 'capture-phase4j-builder-csrf';
		$response = $this->runBuilderEvent(new EventFormBuilderCreate(), [
			'definition_slug' => $definition_slug,
			'title' => 'Builder CSRF',
		], includeCsrfToken: false);

		$this->assertSame(403, $response['http_code']);
		$this->assertFalse($response['body']['ok']);
		$this->assertNull(EntityFormDefinition::findBySlug($definition_slug));
	}

	public function testBuilderPublishEventPublishesActiveDraftWithExplicitVersion(): void
	{
		$definition_slug = 'capture-phase4j-builder-event-publish';
		$created = (new FormCaptureAuthoringService())->createDefinition($definition_slug, 'Builder event publish');
		$draft_version_id = (int)($created['active_draft']['version_id'] ?? 0);
		$response = $this->runBuilderEvent(new EventFormBuilderPublish(), [
			'definition_slug' => $definition_slug,
			'version_id' => (string)$draft_version_id,
		]);
		$definition = EntityFormDefinition::findBySlug($definition_slug);

		$this->assertSame(200, $response['http_code']);
		$this->assertTrue($response['body']['ok']);
		$this->assertSame('published', $response['body']['data']['action'] ?? null);
		$this->assertSame($draft_version_id, (int)($response['body']['data']['published_version_id'] ?? 0));
		$this->assertInstanceOf(EntityFormDefinition::class, $definition);
		$this->assertSame($draft_version_id, (int)$definition->published_version_id);
	}

	public function testBuilderPublishEventRejectsAbandonedExplicitVersion(): void
	{
		$definition_slug = 'capture-phase4j-builder-event-stale';
		$service = new FormCaptureAuthoringService();
		$created = $service->createDefinition($definition_slug, 'Builder event stale');
		$first = $service->saveDraft($definition_slug, $this->descriptorWithTitle('First event draft'), (string)$created['base_server_hash']);
		$first_draft_id = (int)($first['active_draft']['version_id'] ?? 0);
		$second = $service->saveDraft($definition_slug, $this->descriptorWithTitle('Second event draft'), (string)$first['base_server_hash']);
		$second_draft_id = (int)($second['active_draft']['version_id'] ?? 0);
		$response = $this->runBuilderEvent(new EventFormBuilderPublish(), [
			'definition_slug' => $definition_slug,
			'version_id' => (string)$first_draft_id,
		]);
		$definition = EntityFormDefinition::findBySlug($definition_slug);
		$second_version = EntityFormDefinitionVersion::findFirst(['version_id' => $second_draft_id]);

		$this->assertSame(422, $response['http_code']);
		$this->assertFalse($response['body']['ok']);
		$this->assertInstanceOf(EntityFormDefinition::class, $definition);
		$this->assertNull($definition->published_version_id);
		$this->assertSame('draft', (string)$second_version?->status);
	}

	public function testBuilderEventsRequireContentAdminAuthorization(): void
	{
		$events = [
			new EventFormBuilderCreate(),
			new EventFormBuilderPreviewRender(),
			new EventFormBuilderSaveDraft(),
			new EventFormBuilderPublish(),
		];

		$this->impersonate(null);

		foreach ($events as $event) {
			$this->assertFalse($event->authorize(PolicyContext::fromEvent($event))->allow);
		}

		$this->impersonateAndRequireRole('admin_developer', RoleList::ROLE_CONTENT_ADMIN);

		foreach ($events as $event) {
			$this->assertTrue($event->authorize(PolicyContext::fromEvent($event))->allow);
		}
	}

	private function upsertCapture(string $definition_slug, array $security = []): FormDefinitionResolution
	{
		return (new FormCaptureDefinitionRepository())->upsertPublishedDefinition(
			$definition_slug,
			$this->descriptor(),
			array_replace_recursive([
				'honeypot' => [
					'enabled' => true,
					'field_name' => 'company_website',
				],
				'rate_limit' => [
					'accepted' => 5,
					'window_seconds' => 600,
				],
			], $security),
		);
	}

	private function captureForm(FormDefinitionResolution $resolution): CaptureForm
	{
		return new CaptureForm(
			$resolution->definitionSlug(),
			'phase4_capture_' . md5($resolution->definitionSlug()),
			$this->treeContext(),
			'/capture-return',
			[
				'host_page_id' => 1,
				'widget_connection_id' => 2,
				'form_definition_resolution' => $resolution,
			],
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function descriptor(): array
	{
		return [
			'kind' => 'capture',
			'title' => ['text' => 'Contact probe'],
			'description' => ['text' => 'Capture probe'],
			'fields' => [
				[
					'type' => 'text',
					'name' => 'name',
					'key' => 'name_key',
					'label' => ['text' => 'Name'],
					'normalizers' => ['trim', 'collapse-whitespace'],
					'validators' => [
						['type' => 'required', 'message' => ['text' => 'Name required']],
						['type' => 'max_length', 'max' => 120],
					],
				],
				[
					'type' => 'text',
					'name' => 'email',
					'label' => ['text' => 'Email'],
					'normalizers' => ['trim', 'lowercase'],
					'validators' => [
						['type' => 'required'],
						['type' => 'email', 'message' => ['text' => 'Email invalid']],
					],
				],
				[
					'type' => 'select',
					'name' => 'topic',
					'label' => ['text' => 'Topic'],
					'values' => [
						['inputtype' => 'option', 'value' => 'general', 'label' => ['text' => 'General']],
						['inputtype' => 'option', 'value' => 'support', 'label' => ['text' => 'Support']],
					],
					'validators' => [
						['type' => 'enum', 'values' => ['general', 'support']],
					],
				],
				[
					'type' => 'textarea',
					'name' => 'message',
					'label' => ['text' => 'Message'],
					'normalizers' => ['trim'],
					'validators' => [
						['type' => 'required'],
						['type' => 'min_length', 'min' => 10],
					],
				],
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function changedDescriptor(): array
	{
		$descriptor = $this->descriptor();
		$descriptor['title']['text'] = 'Contact probe changed';
		$descriptor['fields'][] = [
			'type' => 'text',
			'name' => 'company',
			'label' => ['text' => 'Company'],
			'normalizers' => ['trim'],
			'validators' => [
				['type' => 'max_length', 'max' => 120],
			],
		];

		return $descriptor;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function descriptorWithTitle(string $title): array
	{
		$descriptor = $this->descriptor();
		$descriptor['title']['text'] = $title;

		return $descriptor;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function defaultSecurity(): array
	{
		return [
			'honeypot' => [
				'enabled' => true,
				'field_name' => 'company_website',
			],
			'rate_limit' => [
				'accepted' => 5,
				'window_seconds' => 600,
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function validPayload(): array
	{
		return [
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'name_key' => 'Ada Lovelace',
			'email' => 'ada@example.test',
			'topic' => 'general',
			'message' => 'This message is long enough.',
			'company_website' => '',
		];
	}

	private function treeContext(): iTreeBuildContext
	{
		return new class () implements iTreeBuildContext {
			public function getPageId(): ?int
			{
				return 1;
			}

			public function getPagedata($key)
			{
				return null;
			}

			public function registerRenderedLayoutComponent(iLayoutComponent $layoutComponent): void
			{
			}

			public function getLayoutTypeName(): ?string
			{
				return 'public_empty';
			}

			public function addToTitle(string $addition): void
			{
			}

			public function isEditable(): bool
			{
				return false;
			}

			public function getTheme(): ?AbstractThemeData
			{
				return null;
			}

			public function overrideLayoutType(string $layoutTypeName): void
			{
			}
		};
	}

	private function expectedClientFingerprintHash(string $value, string $context): string
	{
		return hash_hmac('sha256', $context . "\n" . $value, 'form-refactor-phase-4-capture-test-secret');
	}

	private function submitContextForResolution(FormDefinitionResolution $resolution, string $form_instance_id): FormSubmitContext
	{
		return new FormSubmitContext(
			formId: $resolution->definitionSlug(),
			formInstanceId: $form_instance_id,
			itemId: null,
			returnTarget: '/capture-return',
			hostPageId: null,
			widgetConnectionId: null,
			buildId: FormSubmitContext::currentBuildId(),
			formDefinitionVersionId: $resolution->versionId(),
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array{http_code: int|null, body: array<string, mixed>|null, output: string}
	 */
	private function runSubmitEvent(FormSubmitContext $context, array $payload, bool $includeCsrfToken = true): array
	{
		$post = array_merge($context->toHiddenFields(), $payload);

		if ($includeCsrfToken && !array_key_exists(FormSubmitContext::FIELD_CSRF_TOKEN, $post)) {
			$post[FormSubmitContext::FIELD_CSRF_TOKEN] = $context->issueCsrfToken();
		}

		return $this->runSubmitPost($post);
	}

	/**
	 * @param array<string, mixed> $post
	 * @return array{http_code: int|null, body: array<string, mixed>|null, output: string}
	 */
	private function runSubmitPost(array $post): array
	{
		$this->setRequestContext(
			post: $post,
			server: [
				'HTTP_HOST' => 'localhost',
				'REQUEST_URI' => '/?context=form&event=submit',
				'REQUEST_METHOD' => 'POST',
				'HTTP_ACCEPT' => 'application/json',
				'REMOTE_ADDR' => '203.0.113.44',
				'HTTP_USER_AGENT' => 'Radaptor capture test',
			],
		);

		$ctx = RequestContextHolder::current();
		$ctx->apiResponseCaptureEnabled = true;

		ob_start();
		(new EventFormSubmit())->run();
		$output = (string)ob_get_clean();

		return [
			'http_code' => $ctx->capturedApiResponseHttpCode,
			'body' => $ctx->capturedApiResponse,
			'output' => $output,
		];
	}

	/**
	 * @param array<string, mixed> $post
	 * @return array{http_code: int|null, body: array<string, mixed>|null, output: string}
	 */
	private function runBuilderEvent(AbstractEvent $event, array $post, bool $includeCsrfToken = true): array
	{
		if ($includeCsrfToken && !array_key_exists(FormSubmitContext::FIELD_CSRF_TOKEN, $post)) {
			$post[FormSubmitContext::FIELD_CSRF_TOKEN] = FormSubmitContext::issueCsrfTokenForForm(FormBuilderEventHelper::CSRF_FORM_ID);
		}

		$this->setRequestContext(
			post: $post,
			server: [
				'HTTP_HOST' => 'localhost',
				'REQUEST_URI' => '/?context=form_builder&event=create',
				'REQUEST_METHOD' => 'POST',
				'HTTP_ACCEPT' => 'application/json',
			],
		);

		$ctx = RequestContextHolder::current();
		$ctx->apiResponseCaptureEnabled = true;

		ob_start();
		$event->run();
		$output = (string)ob_get_clean();

		return [
			'http_code' => $ctx->capturedApiResponseHttpCode,
			'body' => $ctx->capturedApiResponse,
			'output' => $output,
		];
	}

	private function restoreAppSecret(): void
	{
		if ($this->_previous_app_secret === false || $this->_previous_app_secret === null) {
			putenv('APP_SECRET');
		} else {
			putenv('APP_SECRET=' . $this->_previous_app_secret);
		}

		$this->_previous_app_secret = null;
	}

	/**
	 * @param array<string, mixed> $get
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $server
	 */
	private function setRequestContext(array $get = [], array $post = [], array $server = []): void
	{
		$server += [
			'HTTP_HOST' => 'localhost',
			'REQUEST_URI' => '/capture-phase-4',
			'REQUEST_METHOD' => $post === [] ? 'GET' : 'POST',
			'REMOTE_ADDR' => '203.0.113.44',
			'HTTP_USER_AGENT' => 'Radaptor capture test',
		];

		$_GET = $get;
		$_POST = $post;
		$_SERVER = $server;
		RequestContextHolder::initializeRequest(get: $get, post: $post, server: $server);
	}

	private function impersonateAndRequireRole(string $username, string $role): void
	{
		$this->impersonate($username);

		if (!Roles::hasRole($role)) {
			self::markTestSkipped("User '{$username}' does not have required role '{$role}'.");
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

		if ($user === null) {
			self::markTestSkipped("Missing test user: {$username}");
		}

		$ctx->currentUser = $user->data();
		$ctx->userSessionInitialized = true;
		Cache::flush(Roles::class);
		Cache::flush(User::class);
	}

	private static function bootstrapConsumerRuntime(): void
	{
		if (self::$_runtime_bootstrapped) {
			return;
		}

		$bootstrap = getenv('RADAPTOR_APP_TEST_BOOTSTRAP') ?: '/app/bootstrap/bootstrap.testing.php';

		if (!is_file($bootstrap)) {
			self::markTestSkipped('Set RADAPTOR_APP_TEST_BOOTSTRAP or run from the Radaptor app container to execute form refactor integration tests.');
		}

		$bootstrap_path = realpath($bootstrap) ?: $bootstrap;
		$included_before = in_array($bootstrap_path, array_map(static fn (string $path): string => realpath($path) ?: $path, get_included_files()), true);

		require_once $bootstrap;

		if (!$included_before) {
			restore_error_handler();
			restore_exception_handler();
		}

		self::$_runtime_bootstrapped = true;
	}
}
