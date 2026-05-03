<?php

declare(strict_types=1);

class EventWidgetUpdate extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return McpCmsAuthoringAuthorization::widgetConnection(
			(int) Request::_POST('connection_id', 0),
			ResourceAcl::_ACL_EDIT,
			$policyContext
		);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'widget.update',
			'group' => 'CMS Authoring',
			'name' => 'Update widget connection',
			'summary' => 'Updates one widget connection by id.',
			'description' => 'Updates a widget connection slot, sequence, attributes, and settings. Attribute and setting objects replace the existing values when provided.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('connection_id', 'body', 'int', true, 'Widget connection id.'),
					BrowserEventDocumentationHelper::param('slot', 'body', 'string', false, 'Target slot name. Omit to keep the current slot.'),
					BrowserEventDocumentationHelper::param('seq', 'body', 'int', false, 'Target sequence. Omit to keep the current sequence.'),
					BrowserEventDocumentationHelper::param('attributes', 'body', 'json-object', false, 'Connection attributes to replace. Omit to leave attributes unchanged.'),
					BrowserEventDocumentationHelper::param('settings', 'body', 'json-object', false, 'Connection settings to replace. Omit to leave settings unchanged.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns the updated widget connection snapshot in data.connection.',
			],
			'authorization' => [
				'visibility' => 'resource ACL',
				'description' => 'Requires edit permission on the webpage that owns the widget connection.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.widget.update',
				'risk' => 'write',
			],
			'notes' => BrowserEventDocumentationHelper::lines(
				'Moving a widget to another slot or sequence recreates the widget connection and returns replaced_connection_id.'
			),
			'side_effects' => BrowserEventDocumentationHelper::lines(
				'Updates widget_connections placement when slot or sequence is provided.',
				'Replaces widget connection attributes and settings when those objects are provided.'
			),
		];
	}

	public function run(): void
	{
		$connection_id = (int) Request::_POST('connection_id', 0);

		if ($connection_id <= 0) {
			ApiResponse::renderError('MISSING_CONNECTION_ID', 'connection_id is required.', 400);

			return;
		}

		try {
			$attributes = self::optionalArray('attributes');
			$settings = self::optionalArray('settings');
			$slot = trim((string) Request::_POST('slot', ''));
			$seq = Request::hasPost('seq') ? (int) Request::_POST('seq') : null;

			ApiResponse::renderSuccess([
				'connection' => CmsResourceSpecService::updateWidgetConnection(
					$connection_id,
					$slot !== '' ? $slot : null,
					$seq,
					$attributes,
					$settings
				),
			]);
		} catch (Throwable $exception) {
			ApiResponse::renderError('WIDGET_UPDATE_FAILED', $exception->getMessage(), 400);
		}
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function optionalArray(string $name): ?array
	{
		if (!Request::hasPost($name)) {
			return null;
		}

		$value = Request::_POST($name, []);

		if (!is_array($value)) {
			throw new InvalidArgumentException("{$name} must be an object.");
		}

		return $value;
	}
}
