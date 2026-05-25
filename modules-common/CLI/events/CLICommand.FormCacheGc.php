<?php

declare(strict_types=1);

class CLICommandFormCacheGc extends AbstractCLICommand
{
	public function getName(): string
	{
		return 'Garbage collect compiled capture form descriptor cache files';
	}

	public function getDocs(): string
	{
		return <<<'DOC'
			Garbage collect compiled capture form descriptor cache files. Defaults to dry-run; pass --apply to delete.

			Usage: radaptor form:cache-gc [--definition-slug <slug>] [--dry-run|--apply] [--json]

			Examples:
			  radaptor form:cache-gc --json
			  radaptor form:cache-gc --definition-slug capture-contact --apply --json
			DOC;
	}

	public function getRiskLevel(): string
	{
		return 'mutation';
	}

	public function run(): void
	{
		$usage = 'Usage: radaptor form:cache-gc [--definition-slug <slug>] [--dry-run|--apply] [--json]';
		CLIOptionHelper::assertNoApplyDryRunConflict($usage);
		$definition_slug = CLIOptionHelper::getOption('definition-slug');
		$dry_run = !Request::hasArg('apply');
		$json = CLIOptionHelper::isJson();

		try {
			$result = (new FormCaptureCompiledDescriptorCacheGarbageCollector())->run(
				$dry_run,
				$definition_slug !== '' ? $definition_slug : null,
			);
		} catch (Throwable $exception) {
			if ($json) {
				CLIOptionHelper::writeJson(['status' => 'error', 'message' => $exception->getMessage()]);

				return;
			}

			echo "Form cache GC failed: {$exception->getMessage()}\n";

			return;
		}

		if ($json) {
			CLIOptionHelper::writeJson($result);

			return;
		}

		echo ($dry_run ? '[dry-run] ' : '')
			. "Matched {$result['matched_files']} cache file(s), "
			. "kept {$result['kept_files']}, "
			. "candidates {$result['delete_candidates']}, "
			. "deleted {$result['deleted_files']}.\n";
	}
}
