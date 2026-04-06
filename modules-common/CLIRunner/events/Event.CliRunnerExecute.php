<?php

declare(strict_types=1);

/**
 * AJAX endpoint for executing CLI commands from the web GUI.
 *
 * Accepts POST with command slug and parameters, validates against
 * CLICommandRegistry, and runs the command via CLICommandWebRunner.
 *
 * URL: ?context=cliRunner&event=execute
 */
class EventCliRunnerExecute extends AbstractEvent implements iAuthorizable, iBrowserEventDocumentable
{
	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'cli_runner.execute',
			'group' => 'Developer Tools',
			'name' => 'Execute web-runnable CLI command',
			'summary' => 'Runs one CLI command through the web runner and returns structured output.',
			'description' => 'Accepts a CLI command slug plus optional arguments/options/flags and delegates to CLICommandWebRunner for execution.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('command', 'body', 'string', true, 'CLI command slug registered as web-runnable.'),
					BrowserEventDocumentationHelper::param('main_arg', 'body', 'string', false, 'Optional main positional argument.'),
					BrowserEventDocumentationHelper::param('options', 'body', 'json-object', false, 'JSON-encoded named options map.'),
					BrowserEventDocumentationHelper::param('flags', 'body', 'json-array', false, 'JSON-encoded list of flag names.'),
					BrowserEventDocumentationHelper::param('extra_args', 'body', 'json-array', false, 'JSON-encoded list of extra positional arguments.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns command output, JSON payload, exit code, duration, and error information.',
			],
			'authorization' => [
				'visibility' => 'role:system_developer',
				'description' => 'Requires the system developer role.',
			],
			'notes' => BrowserEventDocumentationHelper::lines(
				'Only commands that are registered and marked web-runnable can be executed.'
			),
			'side_effects' => BrowserEventDocumentationHelper::lines(
				'Executes a real CLI command and may mutate application state depending on that command.'
			),
		];
	}

	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$command = trim(Request::_POST('command', ''));

		if ($command === '') {
			ApiResponse::renderError('missing_command', 'Command slug is required.', 400);

			return;
		}

		$meta = CLICommandRegistry::getCommandMeta($command);

		if ($meta === null) {
			ApiResponse::renderError('command_not_found', "Command '{$command}' is not available for web execution.", 404);

			return;
		}

		$main_arg = trim(Request::_POST('main_arg', ''));
		$options = $this->_parseOptions();
		$flags = $this->_parseFlags();
		$extra_args = $this->_parseExtraArgs();

		$result = CLICommandWebRunner::execute(
			$command,
			$main_arg,
			$options,
			$flags,
			$meta['timeout'],
			$extra_args
		);

		ApiResponse::renderSuccess([
			'command' => $command,
			'ok' => $result['ok'],
			'output' => $result['output'],
			'output_html' => $result['output_html'],
			'json_data' => $result['json_data'],
			'error' => $result['error'],
			'exit_code' => $result['exit_code'],
			'duration_ms' => $result['duration_ms'],
		]);
	}

	/**
	 * Parse named options from POST data.
	 *
	 * @return array<string, string>
	 */
	private function _parseOptions(): array
	{
		$raw = Request::_POST('options', '');

		if ($raw === '' || !is_string($raw)) {
			return [];
		}

		$decoded = json_decode($raw, true);

		if (!is_array($decoded)) {
			return [];
		}

		$options = [];

		foreach ($decoded as $key => $value) {
			if (is_string($key) && is_scalar($value)) {
				$options[$key] = (string) $value;
			}
		}

		return $options;
	}

	/**
	 * Parse extra positional arguments from POST data.
	 *
	 * @return list<string>
	 */
	private function _parseExtraArgs(): array
	{
		$raw = Request::_POST('extra_args', '');

		if ($raw === '' || !is_string($raw)) {
			return [];
		}

		$decoded = json_decode($raw, true);

		if (!is_array($decoded)) {
			return [];
		}

		$args = [];

		foreach ($decoded as $arg) {
			if (is_scalar($arg)) {
				$args[] = (string) $arg;
			}
		}

		return $args;
	}

	/**
	 * Parse flag list from POST data.
	 *
	 * @return list<string>
	 */
	private function _parseFlags(): array
	{
		$raw = Request::_POST('flags', '');

		if ($raw === '' || !is_string($raw)) {
			return [];
		}

		$decoded = json_decode($raw, true);

		if (!is_array($decoded)) {
			return [];
		}

		$flags = [];

		foreach ($decoded as $flag) {
			if (is_string($flag) && $flag !== '') {
				$flags[] = $flag;
			}
		}

		return $flags;
	}
}
