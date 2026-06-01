<?php

declare(strict_types=1);

final class FormHookOutboundDeliveryAdapter
{
	/**
	 * @param array<string, mixed> $job
	 */
	public function enqueue(array $job): FormHookResult
	{
		if (!class_exists(OutboundDeliveryStorage::class) || !class_exists(OutboundDeliveryJob::class)) {
			return FormHookResult::failed(
				'FORM_HOOK_OUTBOUND_DELIVERY_UNAVAILABLE',
				'Framework outbound delivery queue is not available.',
			);
		}

		try {
			$headers = is_array($job['headers'] ?? null) ? $this->normalizeHeaders($job['headers']) : [];
			$payload = is_array($job['body'] ?? null) ? $job['body'] : [];
			$outbound_job = OutboundDeliveryJob::httpPostJson(
				(string)($job['url'] ?? ''),
				$payload,
				$headers,
				'system',
				null,
				null,
				OutboundDeliveryJob::DEFAULT_MAX_ATTEMPTS,
				(string)($job['job_id'] ?? '') !== '' ? (string)$job['job_id'] : null,
			);
			OutboundDeliveryStorage::enqueue($outbound_job);

			return FormHookResult::queued([
				'outbound_adapter' => OutboundDeliveryStorage::class . '::enqueue',
				'job_id' => $outbound_job->jobId,
			]);
		} catch (Throwable $exception) {
			return FormHookResult::failed(
				'FORM_HOOK_OUTBOUND_ENQUEUE_FAILED',
				$exception->getMessage(),
				['outbound_adapter' => OutboundDeliveryStorage::class . '::enqueue'],
			);
		}
	}

	/**
	 * @param array<mixed> $headers
	 * @return array<string, string>
	 */
	private function normalizeHeaders(array $headers): array
	{
		$normalized = [];

		foreach ($headers as $name => $value) {
			if (is_string($name) && (is_scalar($value) || $value === null)) {
				$normalized[$name] = (string)$value;
			}
		}

		return $normalized;
	}
}
