<?php

declare(strict_types=1);

final class WidgetCaptureFormList extends AbstractWidget
{
	public const string ID = 'capture_form_list';

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
			'path' => '/admin/forms/',
			'resource_name' => 'index.html',
			'layout' => LayoutTypeAdminDefault::ID,
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		$source_filter = (string)Request::_GET('source', 'custom');
		$service = new FormCaptureAuthoringService();

		return $this->createComponentTree('captureFormList', [
			'state' => $service->buildListState($source_filter),
			'csrf_token' => FormSubmitContext::issueCsrfTokenForForm(FormBuilderEventHelper::CSRF_FORM_ID),
			'urls' => [
				'create' => Url::getUrl('form_builder.create'),
				'editor_fragment' => Url::getUrl('form_builder.editor_fragment'),
				'hooks_list' => Url::getUrl('form_hooks.list'),
				'hooks_save' => Url::getUrl('form_hooks.save'),
				'hooks_delete' => Url::getUrl('form_hooks.delete'),
				'hooks_deliveries' => Url::getUrl('form_hooks.deliveries'),
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
			'form.list.title',
			'form.list.subtitle',
			'form.list.tab.custom',
			'form.list.tab.system',
			'form.list.new_slug',
			'form.list.new_title',
			'form.list.create',
			'form.list.empty_custom',
			'form.list.empty_system',
			'form.list.col.slug',
			'form.list.col.source',
			'form.list.col.status',
			'form.list.col.version',
			'form.list.col.usage',
			'form.list.col.actions',
			'form.list.action.edit',
			'form.list.action.view',
			'form.list.editor_title',
			'form.list.editor_loading',
			'form.list.editor_load_failed',
			'form.list.close',
			'form.list.status.draft',
			'form.list.status.published',
			'form.list.status.unknown',
			'form.list.version.none',
			'form.list.version.published',
			'form.list.version.draft',
			'form.list.usage.none',
			'form.builder.placeholder.slug',
			'form.builder.help.slug',
			'form.builder.error_create',
			'form.builder.error_slug_format',
			'form.builder.error_slug_duplicate',
			'form.builder.error_request',
			'form.builder.warning.discard_unsaved',
			'form.builder.action.close',
			'form.builder.panel.hooks',
			'form.builder.hooks.loading',
			'form.builder.hooks.unavailable',
			'form.builder.hooks.empty',
			'form.builder.hooks.target',
			'form.builder.hooks.target_url',
			'form.builder.hooks.target_placeholder',
			'form.builder.hooks.secret',
			'form.builder.hooks.secret_placeholder',
			'form.builder.hooks.secret_configured',
			'form.builder.hooks.enabled',
			'form.builder.hooks.enabled_non_production',
			'form.builder.hooks.preset',
			'form.builder.hooks.preset.custom',
			'form.builder.hooks.create',
			'form.builder.hooks.save',
			'form.builder.hooks.delete',
			'form.builder.hooks.metadata',
			'form.builder.hooks.metadata_key',
			'form.builder.hooks.metadata_value',
			'form.builder.hooks.metadata.to',
			'form.builder.hooks.metadata.subject',
			'form.builder.hooks.metadata.no_field',
			'form.builder.hooks.add_metadata',
			'form.builder.hooks.remove_metadata',
			'form.builder.hooks.excluded_fields',
			'form.builder.hooks.no_fields',
			'form.builder.hooks.recent_logs',
			'form.builder.hooks.log_time',
			'form.builder.hooks.log_status',
			'form.builder.hooks.log_http_status',
			'form.builder.hooks.log_message',
			'form.builder.hooks.no_logs',
			'form.builder.hooks.select_hook',
			'form.builder.hooks.status.saved',
			'form.builder.hooks.status.deleted',
			'form.builder.hooks.error_load',
			'form.builder.hooks.error_save',
			'form.builder.hooks.error_delete',
			'form.builder.hooks.confirm_delete',
		];
		$strings = [];

		foreach ($keys as $key) {
			$strings[$key] = t($key);
		}

		return $strings;
	}
}
