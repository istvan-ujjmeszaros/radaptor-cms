<?php

declare(strict_types=1);

final class FormHookInvocationService
{
	private const int DEFAULT_DELIVERY_RETENTION_DAYS = 30;
	private const int DEFAULT_DELIVERY_PRUNE_LIMIT = 5000;
	private const int SUBMIT_PATH_DELIVERY_PRUNE_LIMIT = 100;

	private static bool $deliveryPrunedThisRequest = false;

	/**
	 * @param array<string, mixed> $payload
	 * @param array<string, mixed> $render_context
	 */
	public function invokeForSubmission(FormDefinitionResolution $resolution, int $submission_id, array $payload, array $render_context): void
	{
		if (!$resolution->isCapture() || $submission_id <= 0 || !$this->tablesInstalled()) {
			return;
		}

		$hooks = EntityFormHookTarget::findEnabledForDefinition($resolution->definitionId());

		if ($hooks === []) {
			return;
		}

		$environment = Kernel::getEnvironment();

		foreach ($hooks as $hook) {
			$this->invokeHook($hook, $resolution, $submission_id, $payload, $render_context, $environment);
		}

		$this->pruneExpiredDeliveriesOnce();
	}

	/**
	 * @return array{
	 *     status: string,
	 *     dry_run: bool,
	 *     older_than_days: int,
	 *     limit: int,
	 *     cutoff: string,
	 *     matched_rows: int,
	 *     deleted_rows: int,
	 *     message?: string
	 * }
	 */
	public function pruneExpiredDeliveries(int $older_than_days = self::DEFAULT_DELIVERY_RETENTION_DAYS, bool $dry_run = true, int $limit = self::DEFAULT_DELIVERY_PRUNE_LIMIT): array
	{
		if ($older_than_days < 0) {
			throw new InvalidArgumentException('older_than_days must be zero or greater.');
		}

		if ($limit < 1) {
			throw new InvalidArgumentException('limit must be at least 1.');
		}

		$cutoff = date('Y-m-d H:i:s', time() - ($older_than_days * 86400));

		if (!$this->tableExists('form_hook_deliveries')) {
			return [
				'status' => 'skipped',
				'dry_run' => $dry_run,
				'older_than_days' => $older_than_days,
				'limit' => $limit,
				'cutoff' => $cutoff,
				'matched_rows' => 0,
				'deleted_rows' => 0,
				'message' => 'form_hook_deliveries table does not exist.',
			];
		}

		$pdo = Db::instance();
		$count = $pdo->prepare('SELECT COUNT(*) FROM form_hook_deliveries WHERE created_at < ?');
		$count->execute([$cutoff]);
		$matched = (int)$count->fetchColumn();
		$deleted = 0;

		if (!$dry_run && $matched > 0) {
			$deleted = $this->deleteExpiredDeliveries($cutoff, $limit);
		}

		return [
			'status' => 'success',
			'dry_run' => $dry_run,
			'older_than_days' => $older_than_days,
			'limit' => $limit,
			'cutoff' => $cutoff,
			'matched_rows' => $matched,
			'deleted_rows' => $deleted,
		];
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param array<string, mixed> $render_context
	 */
	private function invokeHook(
		EntityFormHookTarget $hook,
		FormDefinitionResolution $resolution,
		int $submission_id,
		array $payload,
		array $render_context,
		string $environment,
	): void {
		$hook_payload = $this->payloadForHook($hook, $resolution, $submission_id, $payload, $render_context, $environment);
		$delivery = EntityFormHookDelivery::createFromArray([
			'hook_id' => (int)$hook->hook_id,
			'definition_id' => $resolution->definitionId(),
			'version_id' => $resolution->versionId(),
			'submission_id' => $submission_id,
			'target_kind' => (string)$hook->target_kind,
			'target_label' => (string)$hook->label,
			'status' => 'pending',
			'environment' => $environment,
			'payload_json' => FormCaptureCompiledDescriptorCache::encodeJson($hook_payload),
		]);
		$result = null;

		try {
			if ($environment !== 'production' && !(bool)(int)$hook->enable_in_non_production) {
				$result = FormHookResult::suppressed(
					'FORM_HOOK_SUPPRESSED_NON_PRODUCTION',
					'Capture form hook side effect suppressed outside production.',
					['environment' => $environment],
				);
			} else {
				$target = FormHookTargetRegistry::get((string)$hook->target_kind);

				if (!$target instanceof iFormHookTarget) {
					$result = FormHookResult::failed('FORM_HOOK_UNKNOWN_TARGET_KIND', 'Capture form hook target is not registered.');
				} else {
					$result = $target->invoke(new FormHookInvocation(
						$hook,
						$resolution,
						$submission_id,
						(int)$delivery->delivery_id,
						$environment,
						$hook_payload,
						$this->decodeJsonObject((string)$hook->metadata_json),
					));
				}
			}
		} catch (Throwable $exception) {
			$result = FormHookResult::failed('FORM_HOOK_INVOCATION_FAILED', $exception->getMessage());
		}

		$this->completeDelivery((int)$delivery->delivery_id, $result);
	}

	private function completeDelivery(int $delivery_id, FormHookResult $result): void
	{
		$now = date('Y-m-d H:i:s');
		EntityFormHookDelivery::updateById($delivery_id, [
			'status' => $result->status(),
			'result_json' => FormCaptureCompiledDescriptorCache::encodeJson($result->details()),
			'error_code' => $result->errorCode(),
			'error_message' => $result->errorMessage(),
			'queued_at' => $result->status() === FormHookResult::STATUS_QUEUED ? $now : null,
			'completed_at' => $now,
		]);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param array<string, mixed> $render_context
	 * @return array<string, mixed>
	 */
	private function payloadForHook(
		EntityFormHookTarget $hook,
		FormDefinitionResolution $resolution,
		int $submission_id,
		array $payload,
		array $render_context,
		string $environment,
	): array {
		$excluded = $this->decodeStringList((string)$hook->excluded_field_keys_json);
		$data = $payload;
		unset($data['_submission_id']);

		foreach ($excluded as $field_key) {
			unset($data[$field_key]);
		}

		return [
			'event' => 'capture_form.submitted',
			'environment' => $environment,
			'form' => [
				'definition_id' => $resolution->definitionId(),
				'definition_slug' => $resolution->definitionSlug(),
				'version_id' => $resolution->versionId(),
				'version_number' => (int)($resolution->version()['version_number'] ?? 0),
			],
			'submission' => [
				'submission_id' => $submission_id,
				'locale' => Kernel::getLocale(),
				'host_page_id' => $this->positiveIntOrNull($render_context['host_page_id'] ?? null),
				'widget_connection_id' => $this->positiveIntOrNull($render_context['widget_connection_id'] ?? null),
			],
			'data' => $data,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function decodeJsonObject(string $json): array
	{
		$data = json_decode($json, true);

		return is_array($data) && !array_is_list($data) ? $data : [];
	}

	/**
	 * @return list<string>
	 */
	private function decodeStringList(string $json): array
	{
		$data = json_decode($json, true);

		if (!is_array($data) || !array_is_list($data)) {
			return [];
		}

		return array_values(array_filter(array_map(
			static fn (mixed $value): string => is_scalar($value) ? trim((string)$value) : '',
			$data,
		), static fn (string $value): bool => $value !== ''));
	}

	private function positiveIntOrNull(mixed $value): ?int
	{
		$value = (int)$value;

		return $value > 0 ? $value : null;
	}

	private function pruneExpiredDeliveriesOnce(): void
	{
		if (self::$deliveryPrunedThisRequest) {
			return;
		}

		self::$deliveryPrunedThisRequest = true;

		try {
			if (!$this->tableExists('form_hook_deliveries')) {
				return;
			}

			$cutoff = date('Y-m-d H:i:s', time() - (self::DEFAULT_DELIVERY_RETENTION_DAYS * 86400));
			$this->deleteExpiredDeliveries($cutoff, self::SUBMIT_PATH_DELIVERY_PRUNE_LIMIT);
		} catch (Throwable $exception) {
			Kernel::logException($exception, 'Capture form hook delivery pruning failed');
		}
	}

	private function deleteExpiredDeliveries(string $cutoff, int $limit): int
	{
		$delete = Db::instance()->prepare("DELETE FROM form_hook_deliveries WHERE created_at < ? ORDER BY created_at ASC, delivery_id ASC LIMIT {$limit}");
		$delete->execute([$cutoff]);

		return $delete->rowCount();
	}

	private function tablesInstalled(): bool
	{
		return $this->tableExists('form_hook_targets') && $this->tableExists('form_hook_deliveries');
	}

	private function tableExists(string $table): bool
	{
		$stmt = Db::instance()->prepare(
			"SELECT 1
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_TYPE = 'BASE TABLE'
			  AND TABLE_NAME = ?"
		);
		$stmt->execute([$table]);

		return (bool)$stmt->fetchColumn();
	}
}
