<?php

declare(strict_types=1);

class EventWebpageUpdate extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return McpCmsAuthoringAuthorization::webpagePath(
			(string) Request::_POST('path', ''),
			ResourceAcl::_ACL_EDIT,
			$policyContext
		);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'webpage.update',
			'group' => 'CMS Authoring',
			'name' => 'Update webpage',
			'summary' => 'Updates a webpage resource.',
			'description' => 'Updates layout, metadata, catcher flag, and optionally widget slots for an existing webpage.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('path', 'body', 'string', true, 'Webpage path.'),
					BrowserEventDocumentationHelper::param('layout', 'body', 'string', false, 'Layout id.'),
					BrowserEventDocumentationHelper::param('title', 'body', 'string', false, 'Page title.'),
					BrowserEventDocumentationHelper::param('description', 'body', 'string', false, 'Meta description.'),
					BrowserEventDocumentationHelper::param('keywords', 'body', 'string', false, 'Meta keywords.'),
					BrowserEventDocumentationHelper::param('catcher', 'body', 'bool', false, 'Whether the page is a catcher page.'),
					BrowserEventDocumentationHelper::param('attributes', 'body', 'json-object', false, 'Additional webpage attributes.'),
					BrowserEventDocumentationHelper::param('slots', 'body', 'json-object', false, 'Widget slots to reconcile.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns updated page id and webpage spec.',
			],
			'authorization' => [
				'visibility' => 'resource ACL',
				'description' => 'Requires edit permission on the webpage.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.webpage.update',
				'risk' => 'write',
			],
			'notes' => [],
			'side_effects' => BrowserEventDocumentationHelper::lines('Updates resource_tree, webpage metadata, and optionally widget slot assignments.'),
		];
	}

	public function run(): void
	{
		$path = trim((string) Request::_POST('path', ''));

		if ($path === '') {
			ApiResponse::renderError('MISSING_PATH', 'path is required.', 400);

			return;
		}

		try {
			if (CmsPathHelper::resolveWebpage($path) === null) {
				throw new RuntimeException("Webpage not found: {$path}");
			}

			$spec = self::buildSpec($path);
			$page_id = CmsResourceSpecService::upsertWebpage($spec);

			ApiResponse::renderSuccess([
				'page_id' => $page_id,
				'spec' => CmsResourceSpecService::exportWebpageSpec($path),
			]);
		} catch (Throwable $exception) {
			ApiResponse::renderError('WEBPAGE_UPDATE_FAILED', $exception->getMessage(), 400);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function buildSpec(string $path): array
	{
		$spec = [
			'path' => $path,
			'attributes' => [],
		];
		$layout = (string) Request::_POST('layout', '');

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

		if (Request::hasPost('slots')) {
			$slots = Request::_POST('slots', []);

			if (!is_array($slots)) {
				throw new InvalidArgumentException('slots must be an object.');
			}

			$spec['slots'] = $slots;
		}

		return $spec;
	}
}
