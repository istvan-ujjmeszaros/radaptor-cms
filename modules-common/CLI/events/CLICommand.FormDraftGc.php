<?php

declare(strict_types=1);

class CLICommandFormDraftGc extends AbstractCLICommand
{
	public function getName(): string
	{
		return 'Garbage collect abandoned capture form drafts';
	}

	public function getDocs(): string
	{
		return <<<'DOC'
			Garbage collect abandoned capture form draft versions. Defaults to dry-run; pass --apply to delete.

			Usage: radaptor form:draft-gc [--older-than-days <days>] [--dry-run|--apply] [--json]

			Examples:
			  radaptor form:draft-gc --json
			  radaptor form:draft-gc --older-than-days 30 --apply --json
			DOC;
	}

	public function getRiskLevel(): string
	{
		return 'mutation';
	}

	public function run(): void
	{
		$usage = 'Usage: radaptor form:draft-gc [--older-than-days <days>] [--dry-run|--apply] [--json]';
		CLIOptionHelper::assertNoApplyDryRunConflict($usage);
		$older_than_days = CLIOptionHelper::getNullableIntOption('older-than-days') ?? 30;
		$dry_run = !Request::hasArg('apply');
		$json = CLIOptionHelper::isJson();

		try {
			$result = $dry_run
				? (new FormCaptureDraftGarbageCollector())->run($older_than_days, true)
				: CmsMutationAuditService::withContext(
					'form:draft-gc',
					['older_than_days' => $older_than_days],
					static fn (): array => (new FormCaptureDraftGarbageCollector())->run($older_than_days, false)
				);
		} catch (Throwable $exception) {
			if ($json) {
				CLIOptionHelper::writeJson(['status' => 'error', 'message' => $exception->getMessage()]);

				return;
			}

			echo "Form draft GC failed: {$exception->getMessage()}\n";

			return;
		}

		if ($json) {
			CLIOptionHelper::writeJson($result);

			return;
		}

		echo ($dry_run ? '[dry-run] ' : '')
			. "Matched {$result['matched_rows']} abandoned draft row(s), "
			. "deleted {$result['deleted_rows']}.\n";
	}
}
