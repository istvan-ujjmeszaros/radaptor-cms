<?php

declare(strict_types=1);

final class EventFormHooksDelete extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormHookEventHelper::authorizeConfigurator($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		$documentation = EventFormHookDelete::describeBrowserEvent();
		$documentation['event_name'] = 'form_hooks.delete';

		return $documentation;
	}

	public function run(): void
	{
		(new EventFormHookDelete())->run();
	}
}
