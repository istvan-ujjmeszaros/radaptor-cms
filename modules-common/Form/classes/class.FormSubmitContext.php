<?php

declare(strict_types=1);

final class FormSubmitContext
{
	public const string FIELD_FORM_ID = 'form_id';
	public const string FIELD_FORM_INSTANCE_ID = 'form_instance_id';
	public const string FIELD_ITEM_ID = 'item_id';
	public const string FIELD_RETURN_TARGET = 'return_target';
	public const string FIELD_HOST_PAGE_ID = 'host_page_id';
	public const string FIELD_WIDGET_CONNECTION_ID = 'widget_connection_id';
	public const string FIELD_BUILD_ID = 'form_build_id';
	public const string FIELD_CONTEXT_PARAMS = 'form_context_params';
	public const string FIELD_FORM_DEFINITION_VERSION_ID = 'form_definition_version_id';
	public const string FIELD_FORM_RENDER_STATE_ID = 'form_render_state_id';
	public const string FIELD_CSRF_TOKEN = 'csrf_token';
	public const string SESSION_KEY_CSRF_TOKENS = 'formCsrfTokens';
	public const string SESSION_KEY_RENDER_STATES = 'formRenderStates';
	public const int CSRF_TOKEN_TTL_SECONDS = 7200;
	public const int CSRF_TOKEN_BAG_LIMIT = 64;

	/**
	 * @param array<string, mixed> $extraParams
	 */
	public function __construct(
		public readonly string $formId,
		public readonly string $formInstanceId,
		public readonly ?int $itemId,
		public readonly string $returnTarget,
		public readonly ?int $hostPageId,
		public readonly ?int $widgetConnectionId,
		public readonly string $buildId,
		public readonly ?int $formDefinitionVersionId = null,
		public readonly array $extraParams = [],
	) {
	}

	/**
	 * @param array<string, mixed> $renderContext
	 */
	public static function fromForm(AbstractForm $form, array $renderContext = []): self
	{
		$get = Request::getGET();
		unset(
			$get['context'],
			$get['event'],
			$get[self::FIELD_FORM_ID],
			$get[self::FIELD_FORM_INSTANCE_ID],
			$get[self::FIELD_ITEM_ID],
			$get[self::FIELD_RETURN_TARGET],
			$get[self::FIELD_HOST_PAGE_ID],
			$get[self::FIELD_WIDGET_CONNECTION_ID],
			$get[self::FIELD_BUILD_ID],
			$get[self::FIELD_CONTEXT_PARAMS],
			$get[self::FIELD_FORM_DEFINITION_VERSION_ID],
			$get[self::FIELD_FORM_RENDER_STATE_ID],
		);

		$item_id = $form->getItemId();
		$host_page_id = self::positiveIntOrNull($renderContext['host_page_id'] ?? $form->getTreeBuildContext()->getPageId());
		$widget_connection_id = self::positiveIntOrNull($renderContext['widget_connection_id'] ?? null);
		$return_target = Url::sanitizeRefererUrl((string)($renderContext['return_target'] ?? $form->getReferer()));
		$form_definition_version_id = self::positiveIntOrNull($renderContext[self::FIELD_FORM_DEFINITION_VERSION_ID] ?? null);
		$resolution = $renderContext['form_definition_resolution'] ?? null;

		if ($form_definition_version_id === null && $resolution instanceof FormDefinitionResolution && $resolution->isCapture()) {
			$form_definition_version_id = $resolution->versionId();
		}

		return new self(
			formId: $form->getFormType(),
			formInstanceId: $form->getFormInstanceId(),
			itemId: $item_id,
			returnTarget: $return_target,
			hostPageId: $host_page_id,
			widgetConnectionId: $widget_connection_id,
			buildId: self::currentBuildId(),
			formDefinitionVersionId: $form_definition_version_id,
			extraParams: $get,
		);
	}

	/**
	 * @param array<string, mixed> $post
	 */
	public static function fromPost(array $post): ?self
	{
		$form_id = trim((string)($post[self::FIELD_FORM_ID] ?? ''));
		$form_instance_id = trim((string)($post[self::FIELD_FORM_INSTANCE_ID] ?? ''));

		if ($form_id === '' || $form_instance_id === '') {
			return null;
		}

		$extra_params = self::decodeContextParams((string)($post[self::FIELD_CONTEXT_PARAMS] ?? ''));
		$item_id = self::positiveIntOrNull($post[self::FIELD_ITEM_ID] ?? null);

		return new self(
			formId: $form_id,
			formInstanceId: $form_instance_id,
			itemId: $item_id,
			returnTarget: Url::sanitizeRefererUrl((string)($post[self::FIELD_RETURN_TARGET] ?? '')),
			hostPageId: self::positiveIntOrNull($post[self::FIELD_HOST_PAGE_ID] ?? null),
			widgetConnectionId: self::positiveIntOrNull($post[self::FIELD_WIDGET_CONNECTION_ID] ?? null),
			buildId: trim((string)($post[self::FIELD_BUILD_ID] ?? '')),
			formDefinitionVersionId: self::positiveIntOrNull($post[self::FIELD_FORM_DEFINITION_VERSION_ID] ?? null),
			extraParams: $extra_params,
		);
	}

	/**
	 * @return array<string, scalar>
	 */
	public function toHiddenFields(): array
	{
		return [
			self::FIELD_FORM_ID => $this->formId,
			self::FIELD_FORM_INSTANCE_ID => $this->formInstanceId,
			self::FIELD_ITEM_ID => $this->itemId ?? '',
			self::FIELD_RETURN_TARGET => $this->returnTarget,
			self::FIELD_HOST_PAGE_ID => $this->hostPageId ?? '',
			self::FIELD_WIDGET_CONNECTION_ID => $this->widgetConnectionId ?? '',
			self::FIELD_BUILD_ID => $this->buildId,
			self::FIELD_CONTEXT_PARAMS => self::encodeContextParams($this->extraParams),
			self::FIELD_FORM_DEFINITION_VERSION_ID => $this->formDefinitionVersionId ?? '',
			self::FIELD_FORM_RENDER_STATE_ID => $this->formDefinitionVersionId !== null ? $this->issueRenderStateId() : '',
		];
	}

	public function issueCsrfToken(): string
	{
		return self::issueCsrfTokenForForm($this->formId);
	}

	/**
	 * @param array<string, mixed> $post
	 */
	public function validateCsrfToken(array $post): ?ApiError
	{
		return self::validateCsrfTokenForForm($this->formId, $post[self::FIELD_CSRF_TOKEN] ?? null);
	}

	/**
	 * @param array<string, mixed> $post
	 */
	public function validateRenderState(array $post): ?ApiError
	{
		if ($this->formDefinitionVersionId === null) {
			return null;
		}

		$render_state_id = is_scalar($post[self::FIELD_FORM_RENDER_STATE_ID] ?? null)
			? trim((string)$post[self::FIELD_FORM_RENDER_STATE_ID])
			: '';

		if ($render_state_id === '') {
			return self::renderStateError('missing');
		}

		$now = time();
		$bag = self::normalizeRenderStateBag(Request::_SESSION(self::SESSION_KEY_RENDER_STATES, []), $now, false);
		$entry = $bag[$render_state_id] ?? null;

		if (!is_array($entry)) {
			Request::saveSessionData([self::SESSION_KEY_RENDER_STATES], $bag);

			return self::renderStateError('stale');
		}

		if ((int)($entry['expires_at'] ?? 0) <= $now) {
			unset($bag[$render_state_id]);
			Request::saveSessionData([self::SESSION_KEY_RENDER_STATES], $bag);

			return self::renderStateError('expired');
		}

		if (!$this->matchesRenderStateEntry($entry)) {
			Request::saveSessionData([self::SESSION_KEY_RENDER_STATES], $bag);

			return self::renderStateError('mismatch');
		}

		$entry['last_seen_at'] = $now;
		$bag[$render_state_id] = $entry;
		Request::saveSessionData([self::SESSION_KEY_RENDER_STATES], self::limitTimedSessionBag($bag));

		return null;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toRuntimeGet(): array
	{
		// These values originate from hidden form context, so keep treating them
		// exactly like user-controlled GET input after restoring the submit route.
		$get = $this->extraParams;
		$get[self::FIELD_FORM_ID] = $this->formId;

		if ($this->itemId !== null) {
			$get[self::FIELD_ITEM_ID] = (string)$this->itemId;
		} else {
			unset($get[self::FIELD_ITEM_ID]);
		}

		if ($this->returnTarget !== '') {
			$get['referer'] = $this->returnTarget;
		}

		return $get;
	}

	public function isCurrentBuild(): bool
	{
		return $this->buildId === '' || hash_equals(self::currentBuildId(), $this->buildId);
	}

	public function canAccessHostContext(): bool
	{
		if ($this->hostPageId !== null && !ResourceTreeHandler::canAccessResource($this->hostPageId, ResourceAcl::_ACL_VIEW)) {
			return false;
		}

		if ($this->widgetConnectionId !== null) {
			$page_id = WidgetConnection::getOwnerWebpageId($this->widgetConnectionId);

			if ($page_id === null) {
				return false;
			}

			if ($this->hostPageId !== null && (int)$page_id !== $this->hostPageId) {
				return false;
			}

			if (!ResourceTreeHandler::canAccessResource((int)$page_id, ResourceAcl::_ACL_VIEW)) {
				return false;
			}
		}

		return true;
	}

	public static function currentBuildId(): string
	{
		$root = defined('DEPLOY_ROOT') ? rtrim((string) DEPLOY_ROOT, '/') . '/' : '';
		$paths = array_filter([
			$root . 'radaptor.lock.json',
			$root . 'radaptor.json',
			$root . 'composer.lock',
		]);
		$parts = [];

		foreach ($paths as $path) {
			if (is_file($path)) {
				$parts[] = basename($path) . ':' . (string)filemtime($path) . ':' . (string)filesize($path);
			}
		}

		if ($parts === []) {
			$parts[] = 'php:' . PHP_VERSION;
		}

		return substr(sha1(implode('|', $parts)), 0, 16);
	}

	public static function issueCsrfTokenForForm(string $formId): string
	{
		$form_id = trim($formId);
		$now = time();
		$bag = self::normalizeCsrfTokenBag(Request::_SESSION(self::SESSION_KEY_CSRF_TOKENS, []), $now);
		$entry = $bag[$form_id] ?? null;

		if (is_array($entry) && ($entry['token'] ?? '') !== '' && (int)($entry['expires_at'] ?? 0) > $now) {
			$entry['last_seen_at'] = $now;
			$bag[$form_id] = $entry;
			Request::saveSessionData([self::SESSION_KEY_CSRF_TOKENS], $bag);

			return (string)$entry['token'];
		}

		$bag[$form_id] = [
			'token' => bin2hex(random_bytes(32)),
			'expires_at' => $now + self::CSRF_TOKEN_TTL_SECONDS,
			'last_seen_at' => $now,
		];
		$bag = self::limitCsrfTokenBag($bag);
		Request::saveSessionData([self::SESSION_KEY_CSRF_TOKENS], $bag);

		return (string)$bag[$form_id]['token'];
	}

	private function issueRenderStateId(): string
	{
		$now = time();
		$bag = self::normalizeRenderStateBag(Request::_SESSION(self::SESSION_KEY_RENDER_STATES, []), $now);
		$render_state_id = bin2hex(random_bytes(16));

		$bag[$render_state_id] = [
			'form_id' => $this->formId,
			'form_instance_id' => $this->formInstanceId,
			'item_id' => $this->itemId,
			'host_page_id' => $this->hostPageId,
			'widget_connection_id' => $this->widgetConnectionId,
			'build_id' => $this->buildId,
			'form_definition_version_id' => $this->formDefinitionVersionId,
			'expires_at' => $now + self::CSRF_TOKEN_TTL_SECONDS,
			'last_seen_at' => $now,
		];
		Request::saveSessionData([self::SESSION_KEY_RENDER_STATES], self::limitTimedSessionBag($bag));

		return $render_state_id;
	}

	public static function validateCsrfTokenForForm(string $formId, mixed $submittedToken): ?ApiError
	{
		$form_id = trim($formId);
		$submitted_token = is_scalar($submittedToken) ? trim((string)$submittedToken) : '';

		if ($submitted_token === '') {
			return self::csrfError('missing');
		}

		$now = time();
		$bag = self::normalizeCsrfTokenBag(Request::_SESSION(self::SESSION_KEY_CSRF_TOKENS, []), $now, false);
		$entry = $bag[$form_id] ?? null;

		if (!is_array($entry) || ($entry['token'] ?? '') === '') {
			Request::saveSessionData([self::SESSION_KEY_CSRF_TOKENS], $bag);

			return self::csrfError('stale');
		}

		if ((int)($entry['expires_at'] ?? 0) <= $now) {
			unset($bag[$form_id]);
			Request::saveSessionData([self::SESSION_KEY_CSRF_TOKENS], $bag);

			return self::csrfError('expired');
		}

		if (!hash_equals((string)$entry['token'], $submitted_token)) {
			Request::saveSessionData([self::SESSION_KEY_CSRF_TOKENS], $bag);

			return self::csrfError('invalid');
		}

		$entry['last_seen_at'] = $now;
		$bag[$form_id] = $entry;
		Request::saveSessionData([self::SESSION_KEY_CSRF_TOKENS], $bag);

		return null;
	}

	/**
	 * @param array<string, mixed> $params
	 */
	private static function encodeContextParams(array $params): string
	{
		if ($params === []) {
			return '';
		}

		try {
			$json = json_encode($params, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
		} catch (JsonException) {
			return '';
		}

		return is_string($json) ? base64_encode($json) : '';
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function decodeContextParams(string $encoded): array
	{
		if (trim($encoded) === '') {
			return [];
		}

		$decoded = base64_decode($encoded, true);

		if ($decoded === false) {
			return [];
		}

		try {
			$params = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return [];
		}

		return is_array($params) ? $params : [];
	}

	private static function csrfError(string $reason): ApiError
	{
		return new ApiError(
			'FORM_CSRF_INVALID',
			t('response_error.internal.refresh_page'),
			details: ['reason' => $reason],
		);
	}

	private static function renderStateError(string $reason): ApiError
	{
		return new ApiError(
			'FORM_RENDER_STATE_INVALID',
			t('response_error.internal.refresh_page'),
			details: ['reason' => $reason],
		);
	}

	/**
	 * @param mixed $bag
	 * @return array<string, array{token: string, expires_at: int, last_seen_at: int}>
	 */
	private static function normalizeCsrfTokenBag(mixed $bag, int $now, bool $dropExpired = true): array
	{
		if (!is_array($bag)) {
			return [];
		}

		$normalized = [];

		foreach ($bag as $form_id => $entry) {
			if (!is_string($form_id) || !is_array($entry)) {
				continue;
			}

			$token = is_scalar($entry['token'] ?? null) ? (string)$entry['token'] : '';
			$expires_at = (int)($entry['expires_at'] ?? 0);

			if ($token === '' || ($dropExpired && $expires_at <= $now)) {
				continue;
			}

			$normalized[$form_id] = [
				'token' => $token,
				'expires_at' => $expires_at,
				'last_seen_at' => (int)($entry['last_seen_at'] ?? $expires_at),
			];
		}

		return self::limitCsrfTokenBag($normalized);
	}

	/**
	 * @param mixed $bag
	 * @return array<string, array<string, mixed>>
	 */
	private static function normalizeRenderStateBag(mixed $bag, int $now, bool $dropExpired = true): array
	{
		if (!is_array($bag)) {
			return [];
		}

		$normalized = [];

		foreach ($bag as $render_state_id => $entry) {
			if (!is_string($render_state_id) || !is_array($entry)) {
				continue;
			}

			$expires_at = (int)($entry['expires_at'] ?? 0);

			if ($expires_at <= 0 || ($dropExpired && $expires_at <= $now)) {
				continue;
			}

			$normalized[$render_state_id] = [
				'form_id' => is_scalar($entry['form_id'] ?? null) ? (string)$entry['form_id'] : '',
				'form_instance_id' => is_scalar($entry['form_instance_id'] ?? null) ? (string)$entry['form_instance_id'] : '',
				'item_id' => self::positiveIntOrNull($entry['item_id'] ?? null),
				'host_page_id' => self::positiveIntOrNull($entry['host_page_id'] ?? null),
				'widget_connection_id' => self::positiveIntOrNull($entry['widget_connection_id'] ?? null),
				'build_id' => is_scalar($entry['build_id'] ?? null) ? (string)$entry['build_id'] : '',
				'form_definition_version_id' => self::positiveIntOrNull($entry['form_definition_version_id'] ?? null),
				'expires_at' => $expires_at,
				'last_seen_at' => (int)($entry['last_seen_at'] ?? $expires_at),
			];
		}

		return self::limitTimedSessionBag($normalized);
	}

	/**
	 * @param array<string, array{token: string, expires_at: int, last_seen_at: int}> $bag
	 * @return array<string, array{token: string, expires_at: int, last_seen_at: int}>
	 */
	private static function limitCsrfTokenBag(array $bag): array
	{
		if (count($bag) <= self::CSRF_TOKEN_BAG_LIMIT) {
			return $bag;
		}

		uasort(
			$bag,
			static fn (array $a, array $b): int => ((int)$a['last_seen_at'] <=> (int)$b['last_seen_at'])
				?: ((int)$a['expires_at'] <=> (int)$b['expires_at'])
		);

		return array_slice($bag, -self::CSRF_TOKEN_BAG_LIMIT, null, true);
	}

	/**
	 * @param array<string, array<string, mixed>> $bag
	 * @return array<string, array<string, mixed>>
	 */
	private static function limitTimedSessionBag(array $bag): array
	{
		if (count($bag) <= self::CSRF_TOKEN_BAG_LIMIT) {
			return $bag;
		}

		uasort(
			$bag,
			static fn (array $a, array $b): int => ((int)($a['last_seen_at'] ?? 0) <=> (int)($b['last_seen_at'] ?? 0))
				?: ((int)($a['expires_at'] ?? 0) <=> (int)($b['expires_at'] ?? 0))
		);

		return array_slice($bag, -self::CSRF_TOKEN_BAG_LIMIT, null, true);
	}

	/**
	 * @param array<string, mixed> $entry
	 */
	private function matchesRenderStateEntry(array $entry): bool
	{
		return hash_equals((string)($entry['form_id'] ?? ''), $this->formId)
			&& hash_equals((string)($entry['form_instance_id'] ?? ''), $this->formInstanceId)
			&& hash_equals((string)($entry['build_id'] ?? ''), $this->buildId)
			&& (int)($entry['form_definition_version_id'] ?? 0) === $this->formDefinitionVersionId
			&& (self::positiveIntOrNull($entry['item_id'] ?? null) === $this->itemId)
			&& (self::positiveIntOrNull($entry['host_page_id'] ?? null) === $this->hostPageId)
			&& (self::positiveIntOrNull($entry['widget_connection_id'] ?? null) === $this->widgetConnectionId);
	}

	private static function positiveIntOrNull(mixed $value): ?int
	{
		if ($value === null || $value === '') {
			return null;
		}

		$value = (int)$value;

		return $value > 0 ? $value : null;
	}
}
