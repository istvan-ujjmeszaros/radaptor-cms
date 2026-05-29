<?php

declare(strict_types=1);

final class EventFormHooksList extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormHookEventHelper::authorizeConfigurator($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		$documentation = EventFormHookTargets::describeBrowserEvent();
		$documentation['event_name'] = 'form_hooks.list';

		return $documentation;
	}

	public function run(): void
	{
		(new EventFormHookTargets())->run();
	}
}
