<?php

declare(strict_types=1);

final class EventFormEditorUndo extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return FormBuilderEventHelper::authorizeContentAdmin($policyContext);
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'form_editor.undo',
			'group' => 'CMS Authoring',
			'name' => 'Undo capture form edit',
			'summary' => 'Reverts the working capture form draft to the previous server-side edit-history state.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('form_edit_history', 'body', 'base64-json', true, 'History action payload.'),
				],
			],
			'response' => [
				'kind' => 'redirect|api-json',
				'content_type' => 'text/html or application/json',
			],
			'authorization' => [
				'visibility' => 'role:content_admin',
			],
			'side_effects' => BrowserEventDocumentationHelper::lines('Replaces the active draft version with the previous edit-history descriptor.'),
		];
	}

	public function run(): void
	{
		FormEditorHistoryEventHelper::run('undo');
	}
}
