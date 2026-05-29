<?php

declare(strict_types=1);

final class EventFormHooksDeliveries extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormHookEventHelper::authorizeConfigurator($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		$documentation = EventFormHookDeliveries::describeBrowserEvent();
		$documentation['event_name'] = 'form_hooks.deliveries';

		return $documentation;
	}

	public function run(): void
	{
		(new EventFormHookDeliveries())->run();
	}
}
