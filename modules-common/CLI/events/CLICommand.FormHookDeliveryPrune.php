<?php

declare(strict_types=1);

class CLICommandFormHookDeliveryPrune extends AbstractCLICommand
{
	public function getName(): string
	{
		return 'Prune capture form hook delivery logs';
	}

	public function getDocs(): string
	{
		return <<<'DOC'
			Prune form_hook_deliveries rows older than the retention window. Defaults to dry-run; pass --apply to delete.

			Usage: radaptor form:hook-delivery-prune [--older-than-days <days>] [--limit <limit>] [--dry-run|--apply] [--json]

			Examples:
			  radaptor form:hook-delivery-prune --json
			  radaptor form:hook-delivery-prune --older-than-days 30 --limit 5000 --apply --json
			DOC;
	}

	public function getRiskLevel(): string
	{
		return 'mutation';
	}

	public function run(): void
	{
		$usage = 'Usage: radaptor form:hook-delivery-prune [--older-than-days <days>] [--limit <limit>] [--dry-run|--apply] [--json]';
		CLIOptionHelper::assertNoApplyDryRunConflict($usage);
		$older_than_days = CLIOptionHelper::getNullableIntOption('older-than-days') ?? 30;
		$limit = CLIOptionHelper::getNullableIntOption('limit') ?? 5000;
		$dry_run = !Request::hasArg('apply');
		$json = CLIOptionHelper::isJson();

		try {
			$result = $dry_run
				? (new FormHookInvocationService())->pruneExpiredDeliveries($older_than_days, true, $limit)
				: CmsMutationAuditService::withContext(
					'form:hook-delivery-prune',
					['older_than_days' => $older_than_days, 'limit' => $limit],
					static fn (): array => (new FormHookInvocationService())->pruneExpiredDeliveries($older_than_days, false, $limit)
				);
		} catch (Throwable $exception) {
			if ($json) {
				CLIOptionHelper::writeJson(['status' => 'error', 'message' => $exception->getMessage()]);

				return;
			}

			echo "Form hook delivery prune failed: {$exception->getMessage()}\n";

			return;
		}

		if ($json) {
			CLIOptionHelper::writeJson($result);

			return;
		}

		echo ($dry_run ? '[dry-run] ' : '')
			. "Matched {$result['matched_rows']} form hook delivery row(s), "
			. "deleted {$result['deleted_rows']}.\n";
	}
}
