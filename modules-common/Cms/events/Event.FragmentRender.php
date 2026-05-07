<?php

declare(strict_types=1);

class EventFragmentRender extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return PolicyDecision::allow();
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'fragment.render',
			'group' => 'Runtime',
			'name' => 'Render CMS fragment targets',
			'summary' => 'Renders typed component, slot, and widget targets for partial page navigation.',
			'description' => 'Uses the canonical page URL as page context and returns HTML suitable for htmx swaps.',
			'request' => [
				'method' => 'GET',
				'params' => [
					BrowserEventDocumentationHelper::param('targets', 'query', 'array', false, 'Target list, e.g. slot:content.'),
				],
			],
			'response' => [
				'kind' => 'html-fragment',
				'content_type' => 'text/html',
				'description' => 'Returns a page fragment or OOB target fragments.',
			],
			'authorization' => [
				'visibility' => 'resource ACL',
				'description' => 'Requires view permission on the resolved canonical webpage.',
			],
			'notes' => [],
			'side_effects' => [],
		];
	}

	public function run(): void
	{
		$resource_id = ResourceTreeHandler::getResourceTreeEntryIdFromUrl();

		if (!is_int($resource_id) || $resource_id <= 0 || !ResourceTreeHandler::canAccessResource($resource_id, ResourceAcl::_ACL_VIEW)) {
			$this->fallbackToFullNavigation(404);

			return;
		}

		$resource = ResourceTypeFactory::Factory($resource_id);

		if (!$resource instanceof ResourceTypeWebpage) {
			$this->fallbackToFullNavigation(404);

			return;
		}

		$targets = Request::_GET('targets', []);
		$targets = is_array($targets) ? array_values(array_map('strval', $targets)) : [];

		try {
			WebpageView::header('Content-Type: text/html; charset=UTF-8');
			WebpageView::header('HX-Reswap: none');
			echo (new CmsFragmentRenderer($resource))->renderTargets($targets);
		} catch (Throwable) {
			$this->fallbackToFullNavigation(400);
		}
	}

	private function fallbackToFullNavigation(int $status_code): void
	{
		header('X-Radaptor-Fragment-Fallback: ' . match ($status_code) {
			400 => 'render-error',
			403 => 'forbidden',
			404 => 'not-found',
			default => 'fallback',
		});

		if ((string)($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true') {
			http_response_code(200);
			header('HX-Redirect: ' . $this->canonicalCurrentUrl());

			return;
		}

		http_response_code($status_code);
	}

	private function canonicalCurrentUrl(): string
	{
		$params = Request::getGET();
		unset($params['context'], $params['event'], $params['targets'], $params['folder'], $params['resource']);
		$path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
		$query = http_build_query($params);

		return $query !== '' ? $path . '?' . $query : $path;
	}
}
