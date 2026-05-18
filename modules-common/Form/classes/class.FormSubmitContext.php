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
		);

		$item_id = $form->getItemId();
		$host_page_id = self::positiveIntOrNull($renderContext['host_page_id'] ?? $form->getTreeBuildContext()->getPageId());
		$widget_connection_id = self::positiveIntOrNull($renderContext['widget_connection_id'] ?? null);
		$return_target = Url::sanitizeRefererUrl((string)($renderContext['return_target'] ?? $form->getReferer()));

		return new self(
			formId: $form->getFormType(),
			formInstanceId: $form->getFormInstanceId(),
			itemId: $item_id,
			returnTarget: $return_target,
			hostPageId: $host_page_id,
			widgetConnectionId: $widget_connection_id,
			buildId: self::currentBuildId(),
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
		];
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

	/**
	 * @param array<string, mixed> $params
	 */
	private static function encodeContextParams(array $params): string
	{
		if ($params === []) {
			return '';
		}

		return base64_encode(json_encode($params, JSON_THROW_ON_ERROR));
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

	private static function positiveIntOrNull(mixed $value): ?int
	{
		if ($value === null || $value === '') {
			return null;
		}

		$value = (int)$value;

		return $value > 0 ? $value : null;
	}
}
