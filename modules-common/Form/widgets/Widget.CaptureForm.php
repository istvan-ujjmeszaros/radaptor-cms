<?php

declare(strict_types=1);

class WidgetCaptureForm extends AbstractWidget
{
	public const string ID = 'capture_form';

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
			$resolution = FormDefinitionResolver::resolve($definition_slug);
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

		return [$settings];
	}

	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return true;
	}
}
