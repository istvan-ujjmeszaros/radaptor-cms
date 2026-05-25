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
		];
		$strings = [];

		foreach ($keys as $key) {
			$strings[$key] = t($key);
		}

		return $strings;
	}
}
