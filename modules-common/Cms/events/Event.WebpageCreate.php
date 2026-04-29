<?php

declare(strict_types=1);

class EventWebpageCreate extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return McpCmsAuthoringAuthorization::createWebpage((string) Request::_POST('path', ''), $policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'webpage.create',
			'group' => 'CMS Authoring',
			'name' => 'Create webpage',
			'summary' => 'Creates a webpage resource.',
			'description' => 'Creates a webpage with path, layout, metadata, catcher flag, and optional initial widget slots.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('path', 'body', 'string', true, 'Webpage path.'),
					BrowserEventDocumentationHelper::param('layout', 'body', 'string', true, 'Layout id.'),
					BrowserEventDocumentationHelper::param('title', 'body', 'string', false, 'Page title.'),
					BrowserEventDocumentationHelper::param('description', 'body', 'string', false, 'Meta description.'),
					BrowserEventDocumentationHelper::param('keywords', 'body', 'string', false, 'Meta keywords.'),
					BrowserEventDocumentationHelper::param('catcher', 'body', 'bool', false, 'Whether the page is a catcher page.'),
					BrowserEventDocumentationHelper::param('attributes', 'body', 'json-object', false, 'Additional webpage attributes.'),
					BrowserEventDocumentationHelper::param('slots', 'body', 'json-object', false, 'Initial widget slots.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns created page id and webpage spec.',
			],
			'authorization' => [
				'visibility' => 'resource ACL',
				'description' => 'Requires create permission on the parent folder.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.webpage.create',
				'risk' => 'write',
			],
			'notes' => [],
			'side_effects' => BrowserEventDocumentationHelper::lines('Creates resource_tree and related webpage metadata rows.'),
		];
	}

	public function run(): void
	{
		$path = trim((string) Request::_POST('path', ''));
		$layout = trim((string) Request::_POST('layout', ''));

		if ($path === '') {
			ApiResponse::renderError('MISSING_PATH', 'path is required.', 400);

			return;
		}

		if ($layout === '') {
			ApiResponse::renderError('MISSING_LAYOUT', 'layout is required.', 400);

			return;
		}

		try {
			if (CmsPathHelper::resolveWebpage($path) !== null) {
				throw new RuntimeException("Webpage already exists: {$path}");
			}

			$spec = self::buildSpec($path, true);
			$page_id = CmsResourceSpecService::upsertWebpage($spec);

			ApiResponse::renderSuccess([
				'page_id' => $page_id,
				'spec' => CmsResourceSpecService::exportWebpageSpec($path),
			]);
		} catch (Throwable $exception) {
			ApiResponse::renderError('WEBPAGE_CREATE_FAILED', $exception->getMessage(), 400);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function buildSpec(string $path, bool $require_layout): array
	{
		$spec = [
			'path' => $path,
			'attributes' => [],
		];
		$layout = (string) Request::_POST('layout', '');

		if ($layout === '' && $require_layout) {
			throw new InvalidArgumentException('Missing layout.');
		}

		if ($layout !== '') {
			$spec['layout'] = $layout;
		}

		foreach (['title', 'description', 'keywords'] as $attribute_name) {
			$value = Request::_POST($attribute_name, null);

			if ($value !== null && trim((string) $value) !== '') {
				$spec['attributes'][$attribute_name] = (string) $value;
			}
		}

		$attributes = Request::_POST('attributes', []);

		if (is_array($attributes)) {
			$spec['attributes'] = array_merge($spec['attributes'], $attributes);
		}

		if (Request::hasPost('catcher')) {
			$spec['catcher'] = filter_var(Request::_POST('catcher'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
		}

		$slots = Request::_POST('slots', []);

		if (is_array($slots) && $slots !== []) {
			$spec['slots'] = $slots;
		}

		return $spec;
	}
}
