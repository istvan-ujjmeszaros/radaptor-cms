<?php

declare(strict_types=1);

final class FormHookOutboundDeliveryAdapter
{
	/**
	 * @param array<string, mixed> $job
	 */
	public function enqueue(array $job): FormHookResult
	{
		if (class_exists(OutboundDeliveryStorage::class) && class_exists(OutboundDeliveryJob::class)) {
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

		foreach ($this->candidateCalls($job) as $candidate) {
			$class_name = $candidate['class'];
			$method = $candidate['method'];

			if (!class_exists($class_name) || !method_exists($class_name, $method)) {
				continue;
			}

			try {
				$result = $class_name::$method(...$candidate['args']);

				return FormHookResult::queued([
					'outbound_adapter' => $class_name . '::' . $method,
					'outbound_result' => $this->normalizeResult($result),
				]);
			} catch (TypeError|ArgumentCountError) {
				continue;
			} catch (Throwable $exception) {
				return FormHookResult::failed(
					'FORM_HOOK_OUTBOUND_ENQUEUE_FAILED',
					$exception->getMessage(),
					['outbound_adapter' => $class_name . '::' . $method],
				);
			}
		}

		return FormHookResult::failed(
			'FORM_HOOK_OUTBOUND_DELIVERY_UNAVAILABLE',
			'Framework outbound delivery queue is not available.',
		);
	}

	/**
	 * @param array<string, mixed> $job
	 * @return list<array{class: class-string|string, method: string, args: list<mixed>}>
	 */
	private function candidateCalls(array $job): array
	{
		return [
			['class' => 'OutboundDeliveryQueue', 'method' => 'enqueue', 'args' => [$job]],
			['class' => 'OutboundDeliveryService', 'method' => 'enqueue', 'args' => [$job]],
			['class' => 'OutboundDeliveryOrchestrator', 'method' => 'enqueue', 'args' => [$job]],
			['class' => 'OutboundDeliveryQueue', 'method' => 'enqueueHttpJson', 'args' => [
				$job['url'] ?? '',
				$job['body'] ?? [],
				$job['headers'] ?? [],
				$job['metadata'] ?? [],
			]],
			['class' => 'OutboundDeliveryOrchestrator', 'method' => 'enqueueHttpJson', 'args' => [
				$job['url'] ?? '',
				$job['body'] ?? [],
				$job['headers'] ?? [],
				$job['metadata'] ?? [],
			]],
		];
	}

	/**
	 * @return mixed
	 */
	private function normalizeResult(mixed $result): mixed
	{
		if (is_scalar($result) || $result === null || is_array($result)) {
			return $result;
		}

		if (is_object($result) && method_exists($result, 'toArray')) {
			return $result->toArray();
		}

		return get_debug_type($result);
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
