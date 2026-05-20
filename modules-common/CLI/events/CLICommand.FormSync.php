<?php

declare(strict_types=1);

class CLICommandFormSync extends AbstractCLICommand
{
	public function getName(): string
	{
		return 'Sync shipped capture form descriptors';
	}

	public function getDocs(): string
	{
		return <<<'DOC'
			Sync shipped capture form descriptors from a file or directory. Defaults to dry-run; pass --apply to mutate the database.

			Usage: radaptor form:sync <file-or-directory> [--dry-run|--apply] [--json]

			Discovers *.form.php and *.form.json recursively when a directory is provided.
			The sync is shipped-only and non-destructive. The full batch is validated before apply.

			Examples:
			  radaptor form:sync app/form-definitions
			  radaptor form:sync app/form-definitions/contact.form.php --apply --json
			DOC;
	}

	public function getRiskLevel(): string
	{
		return 'mutation';
	}

	public function run(): void
	{
		$usage = 'Usage: radaptor form:sync <file-or-directory> [--dry-run|--apply] [--json]';
		CLIOptionHelper::assertNoApplyDryRunConflict($usage);
		$path = CLIOptionHelper::getMainArgOrAbort($usage);
		$dry_run = !Request::hasArg('apply');
		$json = CLIOptionHelper::isJson();

		try {
			$result = $dry_run
				? FormCaptureDescriptorSpecLoader::previewSync($path)
				: CmsMutationAuditService::withContext(
					'form:sync',
					['path' => $path, 'source' => 'shipped'],
					static fn (): array => FormCaptureDescriptorSpecLoader::applySync($path)
				);
		} catch (Throwable $exception) {
			if ($json) {
				CLIOptionHelper::writeJson(['status' => 'error', 'message' => $exception->getMessage()]);

				return;
			}

			echo "Form sync failed: {$exception->getMessage()}\n";

			return;
		}

		if ($json) {
			CLIOptionHelper::writeJson($result);

			return;
		}

		$mode = $dry_run ? 'dry-run' : 'apply';
		echo "Form sync ({$mode}): {$result['status']}\n";

		if (isset($result['summary'])) {
			echo 'Summary: ' . json_encode($result['summary'], JSON_UNESCAPED_SLASHES) . "\n";
		}
	}
}
