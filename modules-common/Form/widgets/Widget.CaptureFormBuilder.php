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
		return false;
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
		$service = new FormCaptureAuthoringService();

		return $service->buildBuilderTree(
			(string)Request::_GET('definition_slug', ''),
			(string)Request::_GET('panel', 'properties'),
		);
	}

	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN);
	}

	/**
	 * @return array<string, string>
	 */
	public static function buildStrings(): array
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
			'form.builder.panel.form',
			'form.builder.panel.input',
			'form.builder.panel.properties',
			'form.builder.panel.usage',
			'form.builder.panel.drafts',
			'form.builder.panel.hooks',
			'form.builder.usage.empty',
			'form.builder.usage.page',
			'form.builder.usage.slot',
			'form.builder.usage.connection',
			'form.builder.drafts.empty',
			'form.builder.drafts.current',
			'form.builder.drafts.loaded',
			'form.builder.drafts.version',
			'form.builder.drafts.created',
			'form.builder.drafts.published',
			'form.builder.drafts.note',
			'form.builder.drafts.note_placeholder',
			'form.builder.drafts.status.draft',
			'form.builder.drafts.status.abandoned',
			'form.builder.drafts.status.published',
			'form.builder.no_selection',
			'form.builder.label.definition',
			'form.builder.label.title',
			'form.builder.label.description',
			'form.builder.label.submit_label',
			'form.builder.label.i18n_mode',
			'form.builder.help.i18n_mode',
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
			'form.builder.action.load_draft',
			'form.builder.action.open_translations',
			'form.builder.action.save_draft',
			'form.builder.action.publish',
			'form.builder.action.close',
			'form.builder.status.clean',
			'form.builder.status.dirty',
			'form.builder.status.saving',
			'form.builder.status.saved',
			'form.builder.status.published',
			'form.builder.status.loaded_draft',
			'form.builder.status.draft_note_saved',
			'form.builder.status.conflict',
			'form.builder.status.read_only',
			'form.builder.warning.key_change',
			'form.builder.warning.local_storage',
			'form.builder.warning.reload_or_overwrite',
			'form.builder.warning.load_draft_discard_unsaved',
			'form.builder.action.reload_server',
			'form.builder.action.overwrite_local',
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
			'form.builder.error_create',
			'form.builder.error_slug_format',
			'form.builder.error_slug_duplicate',
			'form.builder.error_duplicate_field_key',
			'form.builder.error_request',
			'form.builder.error_preview',
			'form.builder.error_save',
			'form.builder.error_publish',
			'form.builder.error_load_draft',
			'form.builder.error_draft_note',
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
