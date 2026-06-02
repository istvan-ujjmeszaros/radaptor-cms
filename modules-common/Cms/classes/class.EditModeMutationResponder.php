<?php

declare(strict_types=1);

final class EditModeMutationResponder
{
	/**
	 * @param list<EditModeMutationCommand> $commands
	 * @param array<string, mixed> $data
	 */
	public function succeed(string $message_key, int $page_id, array $commands, array $data = []): void
	{
		$message = t($message_key);
		$payload = $this->payload($message, $commands, $data);

		if ($this->isHtmxMutationRequest()) {
			$this->emitHtmxSuccess($page_id, $commands, $payload);

			return;
		}

		if (Request::wantsNonHtmlResponse()) {
			SystemMessages::flushAllMessages();
			ApiResponse::renderSuccess($payload);

			return;
		}

		SystemMessages::_ok($message);
		Kernel::redirectToReferer();
	}

	public function fail(string $code, string $message_key, int $http_code): void
	{
		$message = t($message_key);

		if ($this->isHtmxMutationRequest()) {
			SystemMessages::flushAllMessages();
			http_response_code($http_code);
			WebpageView::header('Content-Type: text/html; charset=UTF-8');
			WebpageView::header('HX-Reswap: none');
			WebpageView::header('HX-Trigger: ' . json_encode([
				'editModeMutationError' => [
					'code' => $code,
					'message' => $message,
				],
			], JSON_THROW_ON_ERROR));
			echo '<div hidden></div>';

			return;
		}

		if (Request::wantsNonHtmlResponse()) {
			SystemMessages::flushAllMessages();
			ApiResponse::renderErrorObj(new ApiError($code, $message), $http_code);

			return;
		}

		SystemMessages::_error($message);
		Kernel::redirectToReferer();
	}

	/**
	 * @param list<EditModeMutationCommand> $commands
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function payload(string $message, array $commands, array $data): array
	{
		return $data + [
			'message' => $message,
			'commands' => array_map(static fn (EditModeMutationCommand $command): array => $command->toArray(), $commands),
		];
	}

	/**
	 * @param list<EditModeMutationCommand> $commands
	 * @param array<string, mixed> $payload
	 */
	private function emitHtmxSuccess(int $page_id, array $commands, array $payload): void
	{
		SystemMessages::flushAllMessages();
		WebpageView::header('Content-Type: text/html; charset=UTF-8');
		WebpageView::header('HX-Reswap: none');
		WebpageView::header('HX-Trigger: ' . json_encode([
			'editModeMutation' => [
				'message' => (string)($payload['message'] ?? ''),
				'commands' => is_array($payload['commands'] ?? null) ? $payload['commands'] : [],
			],
		], JSON_THROW_ON_ERROR));
		echo $this->renderFragments($page_id, $commands);
	}

	/**
	 * @param list<EditModeMutationCommand> $commands
	 */
	private function renderFragments(int $page_id, array $commands): string
	{
		$resource = ResourceTypeFactory::Factory($page_id);

		if (!$resource instanceof ResourceTypeWebpage) {
			throw new RuntimeException('Edit mode mutation target page is not a webpage.');
		}

		$renderer = new CmsFragmentRenderer($resource);
		$targets = [];
		$element_targets_by_widget = [];

		foreach ($commands as $command) {
			if ($command->operation() !== EditModeMutationCommand::OPERATION_REPLACE) {
				continue;
			}

			if ($command->targetType() === EditModeMutationCommand::TARGET_SLOT) {
				$targets[] = 'slot:' . $command->targetId();

				continue;
			}

			if ($command->targetType() === EditModeMutationCommand::TARGET_WIDGET) {
				$targets[] = 'widget:' . $command->targetId();

				continue;
			}

			if ($command->targetType() === EditModeMutationCommand::TARGET_FORM) {
				$context = $command->context();
				$widget_connection_id = (int)($context['widget_connection_id'] ?? 0);

				if ($widget_connection_id <= 0) {
					throw new InvalidArgumentException('Form mutation command is missing widget_connection_id.');
				}

				$element_targets_by_widget[$widget_connection_id][] = $command->targetId();

				continue;
			}

			if ($command->targetType() === EditModeMutationCommand::TARGET_FORM_FIELD) {
				$context = $command->context();
				$widget_connection_id = (int)($context['widget_connection_id'] ?? 0);

				if ($widget_connection_id <= 0) {
					throw new InvalidArgumentException('Form field mutation command is missing widget_connection_id.');
				}

				$element_targets_by_widget[$widget_connection_id][] = $command->targetId();

				if (is_string($context['panel_target_id'] ?? null) && trim($context['panel_target_id']) !== '') {
					$element_targets_by_widget[$widget_connection_id][] = trim($context['panel_target_id']);
				}
			}
		}

		$html = $targets !== [] ? $renderer->renderTargets($targets) : '';

		foreach ($element_targets_by_widget as $widget_connection_id => $target_ids) {
			$html .= $renderer->renderElementTargetsFromWidget((int)$widget_connection_id, array_values(array_unique($target_ids)));
		}

		return $html !== '' ? $html : '<div hidden></div>';
	}

	private function isHtmxMutationRequest(): bool
	{
		return Request::isHtmxRequest() && !Request::isHtmxBoostedRequest();
	}
}
