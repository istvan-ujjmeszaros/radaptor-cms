<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;

final class FormRefactorPhase2IntegrationTest extends TestCase
{
	private static bool $_runtime_bootstrapped = false;
	private bool $_transaction_started = false;

	protected function setUp(): void
	{
		self::bootstrapConsumerRuntime();

		if (
			!class_exists('AbstractForm')
			|| !class_exists('FormInputText')
			|| !class_exists('FormResult')
			|| !class_exists('RequestContextHolder')
		) {
			self::markTestSkipped('The Radaptor consumer app runtime is required for form refactor integration tests.');
		}

		if (class_exists('Db')) {
			$pdo = Db::instance();

			if (!$pdo->inTransaction()) {
				$pdo->beginTransaction();
				$this->_transaction_started = true;
			}
		}

		$this->ensureProbeFormClass();
		$this->setRequestContext();
		Request::saveSessionData([FormSubmitContext::SESSION_KEY_CSRF_TOKENS], []);
		FormSubmissionStateStore::clear();
		FormTypePhase2Probe::resetProbe();
	}

	protected function tearDown(): void
	{
		if (class_exists('RequestContextHolder', autoload: false)) {
			FormSubmissionStateStore::clear();
			Request::saveSessionData([FormSubmitContext::SESSION_KEY_CSRF_TOKENS], []);
			$this->impersonate(null);
			$this->setRequestContext();
		}

		if ($this->_transaction_started && class_exists('Db', autoload: false)) {
			$pdo = Db::instance();

			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
		}

		$this->_transaction_started = false;
	}

	#[WithoutErrorHandler]
	public function testConstructorDoesNotProcessPostedPayload(): void
	{
		$this->setRequestContext(post: [
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'title_key' => 'Posted title',
		]);

		$form = $this->probeForm();

		$this->assertNull($form->getInput('title')?->getValue());
		$this->assertFalse(FormTypePhase2Probe::$committed);
		$this->assertSame([], $form->savedata);
	}

	public function testProcessBindsStablePayloadKeysAndCompositeValues(): void
	{
		$form = $this->probeForm();

		$result = $form->process([
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'title_key' => 'Stable title',
			'choices' => [
				'alpha' => '1',
			],
			'delivery' => 'ship',
			'author_label' => 'Ada Lovelace',
			'author_id' => '42',
		]);

		$this->assertTrue($result->isSuccess());
		$this->assertTrue(FormTypePhase2Probe::$committed);
		$this->assertSame('Stable title', $form->savedata['title']);
		$this->assertSame(['alpha' => '1'], $form->savedata['choices']);
		$this->assertSame('ship', $form->savedata['delivery']);
		$this->assertSame('Ada Lovelace', $form->savedata['author_label']);
		$this->assertSame('42', $form->savedata['author_id']);
	}

	public function testTreeExposesStableKeysSubmitContextAndAutocompletePairTarget(): void
	{
		$form = $this->probeForm();
		$tree = $form->buildTree();

		$this->assertSame('/?context=form&event=submit', $tree['props']['action']);
		$this->assertSame('Phase2Probe', $tree['props']['form_descriptor_id']);
		$this->assertSame('phase2_probe', $tree['props']['form_instance_id']);
		$this->assertSame('title_key', $tree['props']['field_refs']['title']['key']);
		$this->assertSame('title_key', $tree['props']['field_refs']['title']['name']);
		$this->assertSame('choices', $tree['props']['field_refs']['choices']['key']);
		$this->assertSame('author_id', $tree['props']['field_refs']['author_id']['key']);
		$this->assertSame('Phase2Probe', $tree['props']['submit_context'][FormSubmitContext::FIELD_FORM_ID]);
		$this->assertSame('phase2_probe', $tree['props']['submit_context'][FormSubmitContext::FIELD_FORM_INSTANCE_ID]);
		$this->assertNotSame('', $tree['props']['submit_context'][FormSubmitContext::FIELD_BUILD_ID]);
		$this->assertArrayNotHasKey(FormSubmitContext::FIELD_CSRF_TOKEN, $tree['props']['submit_context']);
		$this->assertArrayHasKey('contents', $tree);
		$this->assertSame($tree['contents'], $tree['slots']);

		$hidden = $tree['contents']['hidden_fields'][0]['props'];
		$this->assertSame('author_id', $hidden['name']);
		$this->assertSame('author_id', $hidden['data_field_key']);
		$csrf = $tree['contents']['hidden_fields'][1]['props'];
		$this->assertSame(FormSubmitContext::FIELD_CSRF_TOKEN, $csrf['name']);
		$this->assertSame(FormSubmitContext::FIELD_CSRF_TOKEN, $csrf['data_field_key']);
		$this->assertNotSame('', $csrf['value']);
		$this->assertFalse($csrf['save']);

		$authorLabel = $tree['contents']['rows'][3]['contents']['content'][0]['props'];
		$this->assertSame('author_label', $authorLabel['name']);
		$this->assertSame('author_id', $authorLabel['connected_autocomplete_field_key']);
	}

	public function testIeMarkerSubmitValueIsRejectedAsInvalidOutcome(): void
	{
		$form = $this->probeForm();

		$result = $form->process([
			'submit_button' => 'legacy image button <!--save--> marker',
			'title_key' => 'Ignored title',
		]);

		$this->assertTrue($result->isInvalid());
		$this->assertArrayHasKey('submit_button', $result->errors());
		$this->assertFalse(FormTypePhase2Probe::$committed);
	}

	public function testDeniedOutcomeIsReturnedWithoutCommitting(): void
	{
		FormTypePhase2Probe::$allow = false;
		$form = $this->probeForm();

		$result = $form->process([
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'title_key' => 'Denied title',
		]);

		$this->assertTrue($result->isDenied());
		$this->assertFalse(FormTypePhase2Probe::$committed);
	}

	public function testClassResolverFindsClassBackedFormTypes(): void
	{
		$this->assertSame(FormTypePhase2Probe::class, FormClassResolver::resolveClassName('Phase2Probe'));
	}

	public function testSubmitContextEncodingSubstitutesInvalidUtf8InsteadOfThrowing(): void
	{
		$context = new FormSubmitContext(
			formId: 'Phase2Probe',
			formInstanceId: 'phase2_probe',
			itemId: null,
			returnTarget: '/phase-2-return',
			hostPageId: null,
			widgetConnectionId: null,
			buildId: FormSubmitContext::currentBuildId(),
			extraParams: [
				'bad' => "\xB1",
			],
		);

		$hidden = $context->toHiddenFields();
		$this->assertNotSame('', $hidden[FormSubmitContext::FIELD_CONTEXT_PARAMS]);

		$round_trip = FormSubmitContext::fromPost($hidden);
		$this->assertInstanceOf(FormSubmitContext::class, $round_trip);
		$this->assertArrayHasKey('bad', $round_trip->extraParams);
		$this->assertIsString($round_trip->extraParams['bad']);
	}

	public function testCsrfTokenIsReusableWithinTtlAndScopedPerDescriptor(): void
	{
		$first = $this->submitContext('Phase2Probe', 'phase3_first');
		$second = $this->submitContext('Phase2Probe', 'phase3_second');
		$other = $this->submitContext('Phase2OtherProbe', 'phase3_other');

		$token = $first->issueCsrfToken();

		$this->assertSame($token, $second->issueCsrfToken());
		$this->assertNotSame($token, $other->issueCsrfToken());
		$this->assertNull($first->validateCsrfToken([
			FormSubmitContext::FIELD_CSRF_TOKEN => $token,
		]));
	}

	public function testNonHtmlEmitterReturnsStructuredInvalidResponse(): void
	{
		$form = $this->probeForm();
		$result = $form->process([
			'submit_button' => 'legacy image button <!--save--> marker',
			'title_key' => 'Invalid title',
		]);
		$context = new FormSubmitContext(
			formId: 'Phase2Probe',
			formInstanceId: 'phase2_probe',
			itemId: null,
			returnTarget: '/phase-2-return',
			hostPageId: 1,
			widgetConnectionId: null,
			buildId: FormSubmitContext::currentBuildId(),
		);
		$this->setRequestContext(
			server: [
				'HTTP_HOST' => 'localhost',
				'REQUEST_URI' => '/?context=form&event=submit',
				'REQUEST_METHOD' => 'POST',
				'HTTP_ACCEPT' => 'application/json',
			]
		);
		$ctx = RequestContextHolder::current();
		$ctx->apiResponseCaptureEnabled = true;

		ob_start();
		(new FormResponseEmitter())->emit($form, $result, $context);
		$output = ob_get_clean();

		$this->assertSame('', $output);
		$this->assertSame(422, $ctx->capturedApiResponseHttpCode);
		$this->assertFalse($ctx->capturedApiResponse['ok']);
		$this->assertSame('FORM_INVALID', $ctx->capturedApiResponse['error']['code']);
		$this->assertSame('invalid', $ctx->capturedApiResponse['meta']['form']['outcome']);
		$this->assertArrayHasKey('submit_button', $ctx->capturedApiResponse['meta']['form']['errors']);
		$this->assertSame([], Request::_SESSION(FormSubmissionStateStore::SESSION_KEY_FLASH_STATES, []));
	}

	public function testSubmitEndpointRunsProbeThroughFullChokepointAndAppliesRuntimeGet(): void
	{
		FormTypePhase2Probe::$requiredUrlParams = [
			'phase_required' => 'Phase required',
		];
		$context = $this->submitContext(
			formId: 'Phase2Probe',
			formInstanceId: 'phase2_event_probe',
			itemId: 7,
			extraParams: [
				'phase_required' => 'present',
			],
		);

		$response = $this->runSubmitEvent($context, [
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'title_key' => '',
			'choices' => [
				'alpha' => '1',
			],
			'delivery' => 'ship',
			'author_id' => '42',
		]);

		$this->assertSame('', $response['output']);
		$this->assertSame(422, $response['http_code']);
		$this->assertFalse($response['body']['ok']);
		$this->assertSame('FORM_INVALID', $response['body']['error']['code']);
		$this->assertSame('invalid', $response['body']['meta']['form']['outcome']);
		$this->assertArrayHasKey('title_key', $response['body']['meta']['form']['errors']);
		$this->assertSame(FormSubmitTreeBuildContext::class, FormTypePhase2Probe::$lastTreeContextClass);
		$this->assertSame(7, FormTypePhase2Probe::$lastItemId);
		$this->assertSame('', FormTypePhase2Probe::$lastTitleValue);
		$this->assertSame('present', Request::_GET('phase_required'));
		$this->assertSame('7', Request::_GET('item_id'));
		$this->assertSame('/phase-2-return', Request::_GET('referer'));
	}

	public function testSubmitEndpointRejectsMissingCsrfTokenBeforeBindingOrProcessing(): void
	{
		$context = $this->submitContext(
			formId: 'Phase2Probe',
			formInstanceId: 'phase3_missing_csrf',
		);

		$response = $this->runSubmitEvent($context, [
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'title_key' => 'Should not bind',
		], includeCsrfToken: false);

		$this->assertSame(403, $response['http_code']);
		$this->assertFalse($response['body']['ok']);
		$this->assertSame('FORM_CSRF_INVALID', $response['body']['error']['code']);
		$this->assertSame('missing', $response['body']['error']['details']['reason']);
		$this->assertSame('denied', $response['body']['meta']['form']['outcome']);
		$this->assertFalse(FormTypePhase2Probe::$committed);
		$this->assertNull(FormTypePhase2Probe::$lastTitleValue);
	}

	public function testSubmitEndpointRejectsInvalidCsrfTokenBeforeBindingOrProcessing(): void
	{
		$context = $this->submitContext(
			formId: 'Phase2Probe',
			formInstanceId: 'phase3_invalid_csrf',
		);
		$context->issueCsrfToken();

		$response = $this->runSubmitEvent($context, [
			FormSubmitContext::FIELD_CSRF_TOKEN => 'not-the-session-token',
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'title_key' => 'Should not bind',
		]);

		$this->assertSame(403, $response['http_code']);
		$this->assertFalse($response['body']['ok']);
		$this->assertSame('FORM_CSRF_INVALID', $response['body']['error']['code']);
		$this->assertSame('invalid', $response['body']['error']['details']['reason']);
		$this->assertFalse(FormTypePhase2Probe::$committed);
		$this->assertNull(FormTypePhase2Probe::$lastTitleValue);
	}

	public function testSubmitEndpointRejectsExpiredCsrfTokenBeforeBindingOrProcessing(): void
	{
		$context = $this->submitContext(
			formId: 'Phase2Probe',
			formInstanceId: 'phase3_expired_csrf',
		);
		$token = $context->issueCsrfToken();
		$bag = Request::_SESSION(FormSubmitContext::SESSION_KEY_CSRF_TOKENS, []);
		$bag[$context->formId]['expires_at'] = time() - 1;
		Request::saveSessionData([FormSubmitContext::SESSION_KEY_CSRF_TOKENS], $bag);

		$response = $this->runSubmitEvent($context, [
			FormSubmitContext::FIELD_CSRF_TOKEN => $token,
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'title_key' => 'Should not bind',
		]);

		$this->assertSame(403, $response['http_code']);
		$this->assertFalse($response['body']['ok']);
		$this->assertSame('FORM_CSRF_INVALID', $response['body']['error']['code']);
		$this->assertSame('expired', $response['body']['error']['details']['reason']);
		$this->assertFalse(FormTypePhase2Probe::$committed);
		$this->assertNull(FormTypePhase2Probe::$lastTitleValue);
	}

	public function testSubmitEndpointDeniesInvalidWidgetHostContextBeforeFormFactory(): void
	{
		$context = $this->submitContext(
			formId: 'Phase2Probe',
			formInstanceId: 'phase2_event_probe',
			widgetConnectionId: 999999999,
		);

		$response = $this->runSubmitEvent($context, [
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'title_key' => 'Denied before factory',
		]);

		$this->assertSame(403, $response['http_code']);
		$this->assertFalse($response['body']['ok']);
		$this->assertSame('FORM_HOST_DENIED', $response['body']['error']['code']);
		$this->assertNull(FormTypePhase2Probe::$lastTreeContextClass);
	}

	public function testUserLoginGoldenFormValidatesThroughSubmitEndpointWithoutWebpageComposer(): void
	{
		$response = $this->runSubmitEvent($this->submitContext(FormList::USERLOGIN, 'phase2_login_golden'), [
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'username' => 'admin_developer',
			'password' => 'wrong-password',
		]);

		$this->assertSame(422, $response['http_code']);
		$this->assertSame('invalid', $response['body']['meta']['form']['outcome']);
		$this->assertArrayHasKey('username', $response['body']['meta']['form']['errors']);
	}

	public function testAclFallbackLoginRedirectsBackToRequestedProtectedUrl(): void
	{
		$login_page_id = ResourceTypeWebpage::getWebpageIdByFormType(FormList::USERLOGIN);

		if (!is_int($login_page_id) || $login_page_id <= 0) {
			self::markTestSkipped('Missing login page for ACL fallback login redirect coverage.');
		}

		$requested_url = 'http://localhost/admin/index.html';
		$context = new FormSubmitContext(
			formId: FormList::USERLOGIN,
			formInstanceId: 'acl_fallback_login',
			itemId: null,
			returnTarget: $requested_url,
			hostPageId: $login_page_id,
			widgetConnectionId: null,
			buildId: FormSubmitContext::currentBuildId(),
		);

		$this->setRequestContext(server: [
			'HTTP_HOST' => 'localhost',
			'REQUEST_URI' => '/admin/index.html',
			'REQUEST_METHOD' => 'GET',
		]);

		$form = Form::factory(
			FormList::USERLOGIN,
			'acl_fallback_login',
			FormSubmitTreeBuildContext::fromSubmitContext($context),
			$context->returnTarget,
			[
				'host_page_id' => $context->hostPageId,
				'return_target' => $context->returnTarget,
			],
		);

		$this->assertSame(
			$requested_url,
			$form->getRedirectTargetForResult(FormResult::success([]), $context),
		);
	}

	public function testCanonicalLoginPageStillRedirectsToRootAfterLogin(): void
	{
		$login_page_id = ResourceTypeWebpage::getWebpageIdByFormType(FormList::USERLOGIN);

		if (!is_int($login_page_id) || $login_page_id <= 0) {
			self::markTestSkipped('Missing login page for canonical login redirect coverage.');
		}

		$this->setRequestContext(server: [
			'HTTP_HOST' => 'localhost',
			'REQUEST_URI' => '/login.html',
			'REQUEST_METHOD' => 'GET',
		]);

		$login_url = Url::getSeoUrl($login_page_id);

		if (!is_string($login_url) || trim($login_url) === '') {
			self::markTestSkipped('Missing login page URL for canonical login redirect coverage.');
		}

		$context = new FormSubmitContext(
			formId: FormList::USERLOGIN,
			formInstanceId: 'canonical_login',
			itemId: null,
			returnTarget: $login_url,
			hostPageId: $login_page_id,
			widgetConnectionId: null,
			buildId: FormSubmitContext::currentBuildId(),
		);
		$form = Form::factory(
			FormList::USERLOGIN,
			'canonical_login',
			FormSubmitTreeBuildContext::fromSubmitContext($context),
			$context->returnTarget,
			[
				'host_page_id' => $context->hostPageId,
				'return_target' => $context->returnTarget,
			],
		);

		$this->assertSame(
			Url::getCurrentHost(),
			$form->getRedirectTargetForResult(FormResult::success([]), $context),
		);
	}

	public function testUserGoldenFormValidatesThroughSubmitEndpointWithoutWebpageComposer(): void
	{
		$this->impersonateAndRequireRole('admin_developer', RoleList::ROLE_USERS_ADMIN);

		$response = $this->runSubmitEvent($this->submitContext(FormList::USER, 'phase2_user_golden'), [
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'username' => '',
			'passwd1' => 'one',
			'passwd2' => 'two',
			'timezone' => 'Not/A_Timezone',
			'locale' => 'en-US',
		], 'admin_developer');

		$this->assertSame(422, $response['http_code']);
		$this->assertSame('invalid', $response['body']['meta']['form']['outcome']);
		$this->assertArrayHasKey('username', $response['body']['meta']['form']['errors']);
	}

	public function testWidgetConnectionSettingsGoldenFormProcessesThroughSubmitEndpointWithoutWebpageComposer(): void
	{
		$connection_id = 9083;

		if (DbHelper::selectOne('widget_connections', ['connection_id' => $connection_id]) === null) {
			self::markTestSkipped('Missing widget connection fixture 9083 for form submit endpoint integration coverage.');
		}

		$this->impersonateAndRequireRole('admin_developer', RoleList::ROLE_CONTENT_ADMIN);

		$response = $this->runSubmitEvent($this->submitContext(FormList::WIDGETCONNECTIONSETTINGS, 'phase2_widget_settings_golden', $connection_id), [
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'widget_width' => 'half',
			'is_last' => '1',
		], 'admin_developer');

		$this->assertSame(200, $response['http_code']);
		$this->assertTrue($response['body']['ok']);
		$this->assertSame('success', $response['body']['data']['form']['outcome']);
	}

	public function testAdminMenuGoldenFormValidatesThroughSubmitEndpointWithoutWebpageComposer(): void
	{
		$this->impersonateAndRequireRole('admin_developer', RoleList::ROLE_SYSTEM_DEVELOPER);

		$response = $this->runSubmitEvent($this->submitContext(FormList::ADMINMENUMENUELEMENT, 'phase2_adminmenu_golden'), [
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'node_name' => '',
			'type' => '',
			'url' => '',
			'page_id' => '',
		], 'admin_developer');

		$this->assertSame(422, $response['http_code']);
		$this->assertSame('invalid', $response['body']['meta']['form']['outcome']);
		$this->assertArrayHasKey('node_name', $response['body']['meta']['form']['errors']);
	}

	public function testClassicHostedInvalidSubmitRedirectsAndRehydratesHostPageOnce(): void
	{
		$page_id = ResourceTypeWebpage::getWebpageIdByFormType(FormList::USERLOGIN);

		if ($page_id <= 0) {
			self::markTestSkipped('Missing login page for classic invalid form submit integration coverage.');
		}

		$connection_id = Widget::getWidgetConnectionId($page_id, ResourceTypeWebpage::DEFAULT_SLOT_NAME, WidgetList::FORM);

		if ($connection_id === null) {
			self::markTestSkipped('Missing login form widget connection for classic invalid form submit integration coverage.');
		}

		$host_url = Url::getSeoUrl($page_id);

		if (!is_string($host_url) || trim($host_url) === '') {
			self::markTestSkipped('Missing login page URL for classic invalid form submit integration coverage.');
		}

		$return_target = 'http://localhost/admin/index.html';
		$context = $this->submitContext(
			formId: FormList::USERLOGIN,
			formInstanceId: md5(FormList::USERLOGIN . '_' . $connection_id),
			hostPageId: $page_id,
			widgetConnectionId: $connection_id,
			returnTarget: $return_target,
		);
		$redirect_target = $context->hostedInvalidRedirectTarget();
		$redirect_query = [];
		parse_str((string)parse_url($redirect_target, PHP_URL_QUERY), $redirect_query);

		$this->assertNotSame($return_target, $redirect_target);
		$this->assertSame((string)parse_url($host_url, PHP_URL_PATH), (string)parse_url($redirect_target, PHP_URL_PATH));
		$this->assertSame($return_target, $redirect_query['referer'] ?? null);
		$post = array_merge($context->toHiddenFields(), [
			FormSubmitContext::FIELD_CSRF_TOKEN => $context->issueCsrfToken(),
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'username' => 'admin_developer',
			'password' => 'wrong-password',
		]);

		$this->setRequestContext(
			post: $post,
			server: [
				'HTTP_HOST' => 'localhost',
				'REQUEST_URI' => '/?context=form&event=submit',
				'REQUEST_METHOD' => 'POST',
				'HTTP_ACCEPT' => 'text/html',
			],
		);
		$this->impersonate('admin_developer');

		ob_start();
		(new EventFormSubmit())->run();
		$output = (string)ob_get_clean();

		$this->assertSame('', $output);
		$this->assertSame(303, http_response_code());
		$this->assertNotSame([], Request::_SESSION(FormSubmissionStateStore::SESSION_KEY_FLASH_STATES, []));

		$host_output = $this->renderWebpageForRequest($page_id, $redirect_target);

		$this->assertStringContainsString('<html', strtolower($host_output));
		$this->assertStringContainsString('Wrong username or password', $host_output);
		$this->assertStringContainsString('name="username"', $host_output);
		$this->assertStringContainsString('data-field-key="username"', $host_output);
		$this->assertStringContainsString('value="admin_developer"', $host_output);
		$this->assertStringContainsString('value="http://localhost/admin/index.html"', $host_output);
		$this->assertSame([], Request::_SESSION(FormSubmissionStateStore::SESSION_KEY_FLASH_STATES, []));

		$second_host_output = $this->renderWebpageForRequest($page_id, $redirect_target);

		$this->assertStringNotContainsString('Wrong username or password', $second_host_output);
		$this->assertStringNotContainsString('value="admin_developer"', $second_host_output);
	}

	public function testSubmissionFlashStateRequiresMatchingHostContextAndIsOneShot(): void
	{
		$context = new FormSubmitContext(
			formId: 'Phase2Probe',
			formInstanceId: 'phase2_probe',
			itemId: null,
			returnTarget: '/phase-2-return',
			hostPageId: 10,
			widgetConnectionId: 20,
			buildId: FormSubmitContext::currentBuildId(),
		);

		FormSubmissionStateStore::flash($context, FormResult::invalid([
			'title_key' => ['Title is required'],
		]), [
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'title_key' => 'Submitted title',
		]);

		$mismatched = $this->probeFormWithRenderContext([
			'host_page_id' => 11,
			'widget_connection_id' => 20,
			'return_target' => '/phase-2-return',
		]);

		$this->assertNull($mismatched->getInput('title')?->getValue());
		$this->assertSame([], $mismatched->getInput('title')?->getErrors());
		$this->assertNotSame([], Request::_SESSION(FormSubmissionStateStore::SESSION_KEY_FLASH_STATES, []));

		$matched = $this->probeFormWithRenderContext([
			'host_page_id' => 10,
			'widget_connection_id' => 20,
			'return_target' => '/phase-2-return',
		]);

		$this->assertSame('Submitted title', $matched->getInput('title')?->getValue());
		$this->assertSame(['Title is required'], $matched->getInput('title')?->getErrors());
		$this->assertSame([], Request::_SESSION(FormSubmissionStateStore::SESSION_KEY_FLASH_STATES, []));

		$second_matched = $this->probeFormWithRenderContext([
			'host_page_id' => 10,
			'widget_connection_id' => 20,
			'return_target' => '/phase-2-return',
		]);

		$this->assertNull($second_matched->getInput('title')?->getValue());
		$this->assertSame([], $second_matched->getInput('title')?->getErrors());
	}

	public function testClassicHostedInvalidSubmitWithoutReturnTargetUsesStandaloneFallback(): void
	{
		$form = $this->probeForm();
		$result = FormResult::invalid([
			'title_key' => ['Title is required'],
		]);
		$context = new FormSubmitContext(
			formId: 'Phase2Probe',
			formInstanceId: 'phase2_probe',
			itemId: null,
			returnTarget: '',
			hostPageId: 1,
			widgetConnectionId: null,
			buildId: FormSubmitContext::currentBuildId(),
		);
		$this->setRequestContext(server: [
			'HTTP_HOST' => 'localhost',
			'REQUEST_URI' => '/?context=form&event=submit',
			'REQUEST_METHOD' => 'POST',
			'HTTP_ACCEPT' => 'text/html',
		]);

		ob_start();
		(new FormResponseEmitter())->emit($form, $result, $context, [
			'submit_button' => AbstractForm::_SUBMIT_VALUE_SAVE,
			'title_key' => '',
		]);
		$output = (string)ob_get_clean();

		$this->assertSame(422, http_response_code());
		$this->assertStringContainsString('<!doctype html>', $output);
		$this->assertStringContainsString('form-submit-errors', $output);
		$this->assertStringContainsString('Title is required', $output);
		$this->assertSame([], Request::_SESSION(FormSubmissionStateStore::SESSION_KEY_FLASH_STATES, []));
	}

	public function testClassicHostedDeniedSubmitDoesNotRedirect(): void
	{
		$form = $this->probeForm();
		$context = new FormSubmitContext(
			formId: 'Phase2Probe',
			formInstanceId: 'phase2_probe',
			itemId: null,
			returnTarget: '/phase-2-return',
			hostPageId: 1,
			widgetConnectionId: null,
			buildId: FormSubmitContext::currentBuildId(),
		);
		$this->setRequestContext(server: [
			'HTTP_HOST' => 'localhost',
			'REQUEST_URI' => '/?context=form&event=submit',
			'REQUEST_METHOD' => 'POST',
			'HTTP_ACCEPT' => 'text/html',
		]);

		ob_start();
		(new FormResponseEmitter())->emit($form, FormResult::denied(new ApiError('FORM_DENIED', 'Denied')), $context);
		$output = (string)ob_get_clean();

		$this->assertSame(403, http_response_code());
		$this->assertStringContainsString('<!doctype html>', $output);
		$this->assertStringContainsString('Denied', $output);
		$this->assertSame([], Request::_SESSION(FormSubmissionStateStore::SESSION_KEY_FLASH_STATES, []));
	}

	public function testHtmxHostedInvalidSubmitStillReturnsFragment(): void
	{
		$form = $this->probeForm();
		$result = FormResult::invalid([
			'title_key' => ['Title is required'],
		]);
		$context = new FormSubmitContext(
			formId: 'Phase2Probe',
			formInstanceId: 'phase2_probe',
			itemId: null,
			returnTarget: '/phase-2-return',
			hostPageId: 1,
			widgetConnectionId: null,
			buildId: FormSubmitContext::currentBuildId(),
		);
		$this->setRequestContext(server: [
			'HTTP_HOST' => 'localhost',
			'REQUEST_URI' => '/?context=form&event=submit',
			'REQUEST_METHOD' => 'POST',
			'HTTP_ACCEPT' => 'text/html',
			'HTTP_HX_REQUEST' => 'true',
		]);

		ob_start();
		(new FormResponseEmitter())->emit($form, $result, $context);
		$output = (string)ob_get_clean();

		$this->assertSame(422, http_response_code());
		$this->assertStringNotContainsString('<!doctype html>', $output);
		$this->assertStringContainsString('form-submit-errors', $output);
		$this->assertStringContainsString('Title is required', $output);
		$this->assertSame([], Request::_SESSION(FormSubmissionStateStore::SESSION_KEY_FLASH_STATES, []));
	}

	private function probeForm(): AbstractForm
	{
		return new FormTypePhase2Probe('Phase2Probe', 'phase2_probe', $this->treeContext(), '/phase-2-return');
	}

	/**
	 * @param array<string, mixed> $renderContext
	 */
	private function probeFormWithRenderContext(array $renderContext): AbstractForm
	{
		return new FormTypePhase2Probe(
			'Phase2Probe',
			'phase2_probe',
			$this->treeContext(),
			(string)($renderContext['return_target'] ?? '/phase-2-return'),
			$renderContext,
		);
	}

	/**
	 * @param array<string, mixed> $extraParams
	 */
	private function submitContext(
		string $formId,
		string $formInstanceId,
		?int $itemId = null,
		array $extraParams = [],
		?int $hostPageId = null,
		?int $widgetConnectionId = null,
		string $returnTarget = '/phase-2-return',
	): FormSubmitContext {
		return new FormSubmitContext(
			formId: $formId,
			formInstanceId: $formInstanceId,
			itemId: $itemId,
			returnTarget: $returnTarget,
			hostPageId: $hostPageId,
			widgetConnectionId: $widgetConnectionId,
			buildId: FormSubmitContext::currentBuildId(),
			extraParams: $extraParams,
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array{http_code: int|null, body: array<string, mixed>|null, output: string}
	 */
	private function runSubmitEvent(FormSubmitContext $context, array $payload, ?string $username = null, bool $includeCsrfToken = true): array
	{
		$post = array_merge($context->toHiddenFields(), $payload);

		if ($includeCsrfToken && !array_key_exists(FormSubmitContext::FIELD_CSRF_TOKEN, $post)) {
			$post[FormSubmitContext::FIELD_CSRF_TOKEN] = $context->issueCsrfToken();
		}

		$this->setRequestContext(
			post: $post,
			server: [
				'HTTP_HOST' => 'localhost',
				'REQUEST_URI' => '/?context=form&event=submit',
				'REQUEST_METHOD' => 'POST',
				'HTTP_ACCEPT' => 'application/json',
			],
		);

		if ($username !== null) {
			$this->impersonate($username);
		}

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
				return 'admin_default';
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

	/**
	 * @param array<string, mixed> $get
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $server
	 */
	private function setRequestContext(array $get = [], array $post = [], array $server = []): void
	{
		$server += [
			'HTTP_HOST' => 'localhost',
			'REQUEST_URI' => '/phase-2-form-refactor',
			'REQUEST_METHOD' => $post === [] ? 'GET' : 'POST',
		];

		$_GET = $get;
		$_POST = $post;
		$_SERVER = $server;
		RequestContextHolder::initializeRequest(get: $get, post: $post, server: $server);
	}

	private function renderWebpageForRequest(int $pageId, string $requestUri): string
	{
		$parsed_url = parse_url($requestUri);

		if (!is_array($parsed_url)) {
			$parsed_url = [];
		}

		$get = [];

		if (isset($parsed_url['query']) && is_string($parsed_url['query'])) {
			parse_str($parsed_url['query'], $get);
		}

		$effective_request_uri = (string)($parsed_url['path'] ?? $requestUri);

		if (isset($parsed_url['query']) && is_string($parsed_url['query']) && $parsed_url['query'] !== '') {
			$effective_request_uri .= '?' . $parsed_url['query'];
		}

		$this->setRequestContext(get: $get, server: [
			'HTTP_HOST' => 'localhost',
			'REQUEST_URI' => $effective_request_uri,
			'REQUEST_METHOD' => 'GET',
			'HTTP_ACCEPT' => 'text/html',
		]);

		$resource = ResourceTypeFactory::Factory($pageId);

		if (!$resource instanceof ResourceTypeWebpage) {
			self::markTestSkipped('Expected login resource to be a webpage.');
		}

		ob_start();
		$resource->view();

		return (string)ob_get_clean();
	}

	private function ensureProbeFormClass(): void
	{
		if (class_exists('FormTypePhase2Probe', autoload: false)) {
			return;
		}

		eval(<<<'PHP'
			final class FormTypePhase2Probe extends AbstractForm
			{
				public static bool $allow = true;
				public static bool $committed = false;
				public static array $requiredUrlParams = [];
				public static ?string $lastTreeContextClass = null;
				public static ?int $lastItemId = null;
				public static ?string $lastTitleValue = null;

				public static function resetProbe(): void
				{
					self::$allow = true;
					self::$committed = false;
					self::$requiredUrlParams = [];
					self::$lastTreeContextClass = null;
					self::$lastItemId = null;
					self::$lastTitleValue = null;
				}

				public static function getRequiredUrlParams(): array
				{
					return self::$requiredUrlParams;
				}

				public static function getName(): string
				{
					return 'Phase 2 probe';
				}

				public static function getDescription(): string
				{
					return 'Phase 2 probe form';
				}

				public static function getListVisibility(): bool
				{
					return true;
				}

				public static function getDefaultPathForCreation(): array
				{
					return [];
				}

				public function hasRole(): bool
				{
					return self::$allow;
				}

				public function __construct(string $_form_type, string $form_id, iTreeBuildContext $_tree_build_context, ?string $referer = null, array $render_context = [])
				{
					parent::__construct($_form_type, $form_id, $_tree_build_context, $referer, $render_context);

					self::$lastTreeContextClass = $_tree_build_context::class;
					self::$lastItemId = $this->getItemId();
				}

				public function commit(): void
				{
					self::$committed = true;
				}

				public function setMetadata(): void
				{
					$this->_meta->enableAutoReferer = false;
				}

				public function makeInputs(): void
				{
					$title = new FormInputText('title', $this);
					$title->key = 'title_key';

					$choices = new FormInputCheckboxgroup('choices', $this);
					$choices->values = [
						'alpha' => 'Alpha',
						'beta' => 'Beta',
					];

					$delivery = new FormInputRadiogroup('delivery', $this);
					$delivery->values = [
						'Ship' => 'ship',
						'Pickup' => 'pickup',
					];

					new FormInputHidden('author_id', $this);

					$authorLabel = new FormInputText('author_label', $this);
					$authorLabel->connected_autocomplete_fieldname = 'author_id';
				}

				protected function _validateData(): void
				{
					parent::_validateData();

					self::$lastTitleValue = (string)$this->getInput('title')->getValue();

					if (self::$lastTitleValue === '') {
						$this->getInput('title')->addError('Title is required');
					}
				}
			}
			PHP);
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
		if (!class_exists('RequestContextHolder', autoload: false)) {
			return;
		}

		$ctx = RequestContextHolder::current();

		if ($username === null) {
			$ctx->currentUser = null;
			$ctx->userSessionInitialized = true;

			if (class_exists('Cache', autoload: false)) {
				Cache::flush(Roles::class);
				Cache::flush(User::class);
			}

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
