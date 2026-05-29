<?php

declare(strict_types=1);

final class EventFormHookDeliveries extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormHookEventHelper::authorizeConfigurator($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form_hook.deliveries',
			'group' => 'CMS Authoring',
			'name' => 'List capture form hook deliveries',
			'summary' => 'Reads recent capture form hook delivery and suppression logs for one form.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('definition_slug', 'query', 'string', true, 'Capture definition slug.'),
					BrowserEventDocumentationHelper::param('limit', 'query', 'int', false, 'Maximum rows, capped at 100.'),
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
			ApiResponse::renderSuccess((new FormHookConfigService())->deliveriesForForm(
				FormHookEventHelper::definitionSlugFromRequest(),
				(int)Request::_GET('limit', 25),
			));
		} catch (FormHookConfigValidationException $exception) {
			FormHookEventHelper::renderException($exception);
		} catch (Throwable) {
			FormHookEventHelper::renderFailure('FORM_HOOK_DELIVERIES_FAILED');
		}
	}
}
