<?php

declare(strict_types=1);

class WidgetCaptureForm extends AbstractWidget
{
	public const string ID = 'capture_form';
	public const array AUTHORING = [
		'insert_mode' => 'manual',
		'reuse' => 'repeatable',
		'surfaces' => ['public'],
		'group' => 'forms',
		'sort' => 10,
	];

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
			'path' => '/',
			'resource_name' => 'capture-form.html',
			'layout' => 'public_empty',
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		$definition_slug = trim((string)$connection->getExtraparam('definition_slug'));

		if ($definition_slug === '') {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => t('form.capture.error_missing_definition'),
			]);
		}

		try {
			$resolution = FormDefinitionResolver::resolveForRender($definition_slug, [
				'structure_editable' => $tree_build_context->isEditable() && Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN),
			]);
		} catch (FormCaptureRuntimeException) {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => t('form.capture.error_unavailable'),
			]);
		}

		if ($resolution === null || !$resolution->isCapture()) {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => t('form.capture.error_unavailable'),
			]);
		}

		$form_instance_id = md5($definition_slug . '_' . $connection->connection_id);
		$render_context = [
			'host_page_id' => $tree_build_context->getPageId(),
			'widget_connection_id' => $connection->connection_id,
			'return_target' => Request::_GET('referer', false)
				? Url::sanitizeRefererUrl((string)Request::_GET('referer'))
				: Url::getCurrentUrlForReferer(),
			'form_definition_resolution' => $resolution,
			'structure_editable' => $resolution->isStructureEditable(),
		];
		$form = Form::factory($definition_slug, $form_instance_id, $tree_build_context, null, $render_context);

		return $form->buildTree();
	}

	public function getEditableCommands(WidgetConnection $connection): array
	{
		if (!Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN)) {
			return [];
		}

		$settings = new WidgetEditCommand();
		$settings->title = t('form.form_settings.title');
		$form_id = defined(FormList::class . '::CAPTUREFORMSETTINGS')
			? (string)constant(FormList::class . '::CAPTUREFORMSETTINGS')
			: 'CaptureFormSettings';
		$settings->url = Form::getSeoUrl($form_id, $connection->connection_id);
		$settings->icon = IconNames::CHOOSE;

		$commands = [$settings];
		$publish = $this->buildPublishCommand($connection);

		if ($publish instanceof WidgetEditCommand) {
			$commands[] = $publish;
		}

		return $commands;
	}

	private function buildPublishCommand(WidgetConnection $connection): ?WidgetEditCommand
	{
		$definition_slug = trim((string)$connection->getExtraparam('definition_slug'));
		$page_id = WidgetConnection::getOwnerWebpageId((int)$connection->connection_id);

		if ($definition_slug === '' || $page_id === null) {
			return null;
		}

		try {
			$resolution = FormDefinitionResolver::resolveForRender($definition_slug, ['structure_editable' => true]);
		} catch (FormCaptureRuntimeException) {
			return null;
		}

		if (!$resolution instanceof FormDefinitionResolution || !$resolution->isStructureEditable() || (string)($resolution->version()['status'] ?? '') !== 'draft') {
			return null;
		}

		$publish = new WidgetEditCommand();
		$publish->title = t('form.builder.action.publish');
		$publish->url = Url::getUrl('form_editor.publish');
		$publish->icon = IconNames::UPLOAD;
		$publish->method = 'post';
		$publish->loader = true;
		$publish->payload = [
			FormSubmitContext::FIELD_CSRF_TOKEN => FormSubmitContext::issueCsrfTokenForForm(FormBuilderEventHelper::CSRF_INLINE_FORM_COMMAND_FORM_ID),
			'definition_slug' => $definition_slug,
			'host_page_id' => $page_id,
			'widget_connection_id' => (int)$connection->connection_id,
			'return_target' => Url::getCurrentUrlForReferer(),
		];

		return $publish;
	}

	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return true;
	}
}
