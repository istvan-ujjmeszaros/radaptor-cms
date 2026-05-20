<?php

declare(strict_types=1);

class CLICommandFormPublish extends AbstractCLICommand
{
	public function getName(): string
	{
		return 'Publish capture form descriptor';
	}

	public function getDocs(): string
	{
		return <<<'DOC'
			Publish one capture form descriptor. Defaults to dry-run; pass --apply to mutate the database.

			Usage: radaptor form:publish <definition-slug> (--descriptor-json <json>|--descriptor-file <file>) [--security-json <json>|--security-file <file>] [--source db|shipped] [--dry-run|--apply] [--json]

			Descriptor files may either return/decode to a raw descriptor object plus --definition-slug,
			or to a wrapper object containing definition_slug, descriptor, optional security, and optional source.

			Examples:
			  radaptor form:publish capture-contact --descriptor-file app/form-definitions/contact.form.json
			  radaptor form:publish capture-contact --descriptor-json '{"kind":"capture","fields":[{"type":"text","name":"name","label":{"text":"Name"}}]}' --apply --json
			DOC;
	}

	public function getRiskLevel(): string
	{
		return 'mutation';
	}

	public function run(): void
	{
		$usage = 'Usage: radaptor form:publish <definition-slug> (--descriptor-json <json>|--descriptor-file <file>) [--security-json <json>|--security-file <file>] [--source db|shipped] [--dry-run|--apply] [--json]';
		CLIOptionHelper::assertNoApplyDryRunConflict($usage);
		$dry_run = !Request::hasArg('apply');
		$json = CLIOptionHelper::isJson();

		try {
			$input = $this->readDescriptorInput($usage);
			$definition_slug = $this->resolveDefinitionSlug($input, $usage);
			$descriptor = $this->resolveDescriptor($input);
			$security = $this->resolveSecurity($input);
			$source = FormCaptureDescriptorSpecLoader::normalizeSource(
				CLIOptionHelper::getOption('source', (string)($input['source'] ?? 'db'))
			);

			$result = $dry_run
				? FormCaptureDescriptorSpecLoader::previewPublish($definition_slug, $descriptor, $security, $source, (string)$input['origin'])
				: CmsMutationAuditService::withContext(
					'form:publish',
					[
						'definition_slug' => $definition_slug,
						'source' => $source,
						'origin' => (string)$input['origin'],
					],
					static fn (): array => FormCaptureDescriptorSpecLoader::applyPublish($definition_slug, $descriptor, $security, $source, (string)$input['origin'])
				);
		} catch (Throwable $exception) {
			if ($json) {
				CLIOptionHelper::writeJson(['status' => 'error', 'message' => $exception->getMessage()]);

				return;
			}

			echo "Form publish failed: {$exception->getMessage()}\n";

			return;
		}

		$this->renderResult($result, $json, 'Form publish');
	}

	/**
	 * @return array<string, mixed>
	 */
	private function readDescriptorInput(string $usage): array
	{
		$descriptor_json = CLIOptionHelper::getOption('descriptor-json');
		$descriptor_file = CLIOptionHelper::getOption('descriptor-file');

		if (($descriptor_json === '' && $descriptor_file === '') || ($descriptor_json !== '' && $descriptor_file !== '')) {
			Kernel::abort($usage);
		}

		if ($descriptor_json !== '') {
			$payload = FormCaptureDescriptorSpecLoader::decodeJsonObject($descriptor_json, 'descriptor-json');
			$payload['origin'] = 'inline';

			return $payload;
		}

		if (!is_readable($descriptor_file)) {
			throw new InvalidArgumentException("Descriptor file is not readable: {$descriptor_file}");
		}

		if (str_ends_with($descriptor_file, '.php')) {
			$payload = (static function (string $path): mixed {
				return require $path;
			})($descriptor_file);

			if (!is_array($payload)) {
				throw new InvalidArgumentException("Descriptor PHP file must return an array: {$descriptor_file}");
			}

			$payload['origin'] = $descriptor_file;

			return $payload;
		}

		$payload = FormCaptureDescriptorSpecLoader::decodeJsonObject((string)file_get_contents($descriptor_file), $descriptor_file);
		$payload['origin'] = $descriptor_file;

		return $payload;
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function resolveDefinitionSlug(array $input, string $usage): string
	{
		$main_arg = Request::getMainArg();
		$definition_slug = is_string($main_arg) && trim($main_arg) !== '' && !str_starts_with(trim($main_arg), '--')
			? trim($main_arg)
			: CLIOptionHelper::getOption('definition-slug', (string)($input['definition_slug'] ?? $input['slug'] ?? ''));

		if ($definition_slug === '') {
			Kernel::abort($usage);
		}

		return $definition_slug;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	private function resolveDescriptor(array $input): array
	{
		$descriptor = $input['descriptor'] ?? $input;

		if (!is_array($descriptor)) {
			throw new InvalidArgumentException('Descriptor payload must be an object.');
		}

		unset($descriptor['definition_slug'], $descriptor['slug'], $descriptor['security'], $descriptor['security_json'], $descriptor['source'], $descriptor['origin']);

		return $descriptor;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|string|null
	 */
	private function resolveSecurity(array $input): array|string|null
	{
		$security_json = CLIOptionHelper::getOption('security-json');
		$security_file = CLIOptionHelper::getOption('security-file');

		if ($security_json !== '' && $security_file !== '') {
			Kernel::abort('--security-json and --security-file are mutually exclusive.');
		}

		if ($security_json !== '') {
			return FormCaptureDescriptorSpecLoader::decodeJsonObject($security_json, 'security-json');
		}

		if ($security_file !== '') {
			if (!is_readable($security_file)) {
				throw new InvalidArgumentException("Security file is not readable: {$security_file}");
			}

			return FormCaptureDescriptorSpecLoader::decodeJsonObject((string)file_get_contents($security_file), $security_file);
		}

		return $input['security'] ?? $input['security_json'] ?? null;
	}

	/**
	 * @param array<string, mixed> $result
	 */
	private function renderResult(array $result, bool $json, string $label): void
	{
		if ($json) {
			CLIOptionHelper::writeJson($result);

			return;
		}

		$mode = !empty($result['dry_run']) ? 'dry-run' : 'apply';
		echo "{$label} ({$mode}): {$result['status']}\n";

		if (isset($result['summary'])) {
			echo 'Summary: ' . json_encode($result['summary'], JSON_UNESCAPED_SLASHES) . "\n";
		}
	}
}
