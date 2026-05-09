<?php

class EventUrlRedirect extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return PolicyDecision::allow();
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'url.redirect',
			'group' => 'Runtime',
			'name' => 'Redirect to SEO URL',
			'summary' => 'Redirect helper that converts a resource id into its SEO URL and issues a redirect.',
			'description' => 'Useful when code only knows a numeric resource id and wants to redirect the browser to the canonical SEO path for that resource.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('id', 'query', 'int', true, 'Resource id to resolve to an SEO URL.'),
				],
			],
			'response' => [
				'kind' => 'redirect',
				'content_type' => 'text/html',
				'description' => 'Returns a redirect response to the resolved SEO URL.',
			],
			'authorization' => [
				'visibility' => 'public',
				'description' => 'No event-level authorization is required; resource ACL is enforced after the redirect target is loaded.',
			],
			'notes' => BrowserEventDocumentationHelper::lines(
				'Use this when you have a resource id and need a browser redirect, not when you already know the final path.'
			),
			'side_effects' => BrowserEventDocumentationHelper::lines(
				'Sends a redirect response immediately.'
			),
		];
	}

	public function run(): void
	{
		$id = Request::_GET("id", Request::DEFAULT_ERROR);
		$url = Url::getSeoUrl((int) $id);

		if ($url === null) {
			ResourceTreeHandler::drop404("Unable to resolve SEO URL for resource {$id}.");
		}

		Url::redirect($url, 301);
	}
}
