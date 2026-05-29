<?php

declare(strict_types=1);

final class EventFormHookTargets extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormHookEventHelper::authorizeConfigurator($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form_hook.targets',
			'group' => 'CMS Authoring',
			'name' => 'List capture form hooks',
			'summary' => 'Lists built-in capture form hook target definitions and configured hooks for one capture form.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('definition_slug', 'query', 'string', true, 'Capture definition slug.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
			],
			'authorization' => [
				'visibility' => 'role:content_admin|system_developer',
			],
		];
	}

	public function run(): void
	{
		try {
			ApiResponse::renderSuccess((new FormHookConfigService())->listForForm(FormHookEventHelper::definitionSlugFromRequest()));
		} catch (FormHookConfigValidationException $exception) {
			FormHookEventHelper::renderException($exception);
		} catch (Throwable) {
			FormHookEventHelper::renderFailure('FORM_HOOK_TARGETS_FAILED');
		}
	}
}
