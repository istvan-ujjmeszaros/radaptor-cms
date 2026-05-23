<?php

declare(strict_types=1);

final class WidgetCaptureFormBuilder extends AbstractWidget
{
	public const string ID = 'capture_form_builder';

	public static function getName(): string
	{
		return t('widget.' . self::ID . '.name');
	}

	public static function getDescription(): string
	{
		return t('widget.' . self::ID . '.description');
	}

	public static function getListVisibility(): bool
	{
		return Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN);
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/forms/edit/',
			'resource_name' => 'index.html',
			'layout' => LayoutTypeAdminEditor::ID,
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		$service = new FormCaptureAuthoringService();
		$state = $service->buildBuilderState((string)Request::_GET('definition_slug', ''));
		$preview = $service->renderPreview(
			(string)($state['selected']['definition']['definition_slug'] ?? 'capture-preview'),
			$state['selected']['descriptor'],
		);

		return $this->createComponentTree('captureFormBuilder', [
			'state' => $state,
			'initial_panel' => (string)Request::_GET('panel', 'properties'),
			'initial_preview' => $preview,
			'initial_preview_html' => $preview['html'] ?? '',
			'csrf_token' => FormSubmitContext::issueCsrfTokenForForm(FormBuilderEventHelper::CSRF_FORM_ID),
			'urls' => [
				'create' => Url::getUrl('form_builder.create'),
				'preview_render' => Url::getUrl('form_builder.preview_render'),
				'save_draft' => Url::getUrl('form_builder.save_draft'),
				'publish' => Url::getUrl('form_builder.publish'),
			],
		], strings: self::buildStrings());
	}

	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN);
	}

	/**
	 * @return array<string, string>
	 */
	private static function buildStrings(): array
	{
		$keys = [
			'form.builder.title',
			'form.builder.new_slug',
			'form.builder.new_title',
			'form.builder.placeholder.slug',
			'form.builder.help.slug',
			'form.builder.create',
			'form.builder.palette',
			'form.builder.preview',
			'form.builder.properties',
			'form.builder.panel.properties',
			'form.builder.panel.usage',
			'form.builder.usage.empty',
			'form.builder.usage.page',
			'form.builder.usage.slot',
			'form.builder.usage.connection',
			'form.builder.no_selection',
			'form.builder.label.definition',
			'form.builder.label.title',
			'form.builder.label.description',
			'form.builder.label.submit_label',
			'form.builder.label.field_label',
			'form.builder.label.field_name',
			'form.builder.label.field_key',
			'form.builder.label.required',
			'form.builder.label.options',
			'form.builder.action.undo',
			'form.builder.action.redo',
			'form.builder.action.move_up',
			'form.builder.action.move_down',
			'form.builder.action.delete',
			'form.builder.action.save_draft',
			'form.builder.action.publish',
			'form.builder.status.clean',
			'form.builder.status.dirty',
			'form.builder.status.saving',
			'form.builder.status.saved',
			'form.builder.status.published',
			'form.builder.status.conflict',
			'form.builder.status.read_only',
			'form.builder.warning.key_change',
			'form.builder.warning.local_storage',
			'form.builder.warning.reload_or_overwrite',
			'form.builder.action.reload_server',
			'form.builder.action.overwrite_local',
			'form.builder.error_create',
			'form.builder.error_slug_format',
			'form.builder.error_slug_duplicate',
			'form.builder.error_request',
			'form.builder.error_preview',
			'form.builder.error_save',
			'form.builder.error_publish',
			'form.capture.honeypot.label',
			'form.capture.submit',
		];
		$strings = [];

		foreach ($keys as $key) {
			$strings[$key] = t($key);
		}

		return $strings;
	}
}
