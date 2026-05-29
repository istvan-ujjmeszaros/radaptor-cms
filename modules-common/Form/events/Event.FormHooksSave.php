<?php

declare(strict_types=1);

final class EventFormHooksSave extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormHookEventHelper::authorizeConfigurator($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		$documentation = EventFormHookSave::describeBrowserEvent();
		$documentation['event_name'] = 'form_hooks.save';

		return $documentation;
	}

	public function run(): void
	{
		(new EventFormHookSave())->run();
	}
}
