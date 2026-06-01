<?php

declare(strict_types=1);

class FormTypeCaptureFormSettings extends AbstractForm
{
	public const string ID = 'capture_form_settings';

	public static function getName(): string
	{
		return t('form.form_settings.name');
	}

	public static function getDescription(): string
	{
		return t('widget.capture_form.description');
	}

	public static function getListVisibility(): bool
	{
		return Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER);
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/forms/',
			'resource_name' => 'capture-form-settings.html',
			'layout' => 'admin_default',
		];
	}

	public static function getRequiredUrlParams(): array
	{
		return [
			'item_id' => t('cms.form.missing_widget_id'),
		];
	}

	public function hasRole(): bool
	{
		$connection_id = $this->connectionId();

		if ($connection_id <= 0 || !$this->isCaptureFormConnection($connection_id)) {
			return false;
		}

		$page_id = WidgetConnection::getOwnerWebpageId($connection_id);

		return $page_id !== null
			&& ResourceAcl::canAccessResource($page_id, ResourceAcl::_ACL_EDIT)
			&& Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN);
	}

	public function commit(): void
	{
		$connection_id = $this->connectionId();

		if ($connection_id <= 0 || !$this->isCaptureFormConnection($connection_id)) {
			throw new RuntimeException(t('cms.form.missing_widget_id'));
		}

		AttributeHandler::addAttribute(
			new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, (string) $connection_id),
			[
				'definition_slug' => (string)($this->savedata['definition_slug'] ?? ''),
			],
		);
	}

	public function setMetadata(): void
	{
		$this->_meta->title = t('form.form_settings.title');
		$this->_meta->sub_title = t('cms.form.settings_subtitle', [
			'page' => WidgetConnection::getOwnerWebpageTitle($this->connectionId()),
		]);
	}

	public function setInitValues(): void
	{
		$this->initvalues = AttributeHandler::getAttributes(
			new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, (string) $this->connectionId())
		);
	}

	public function makeInputs(): void
	{
		$definition_slug = new FormInputSelect('definition_slug', $this);
		$definition_slug->label = t('widget.capture_form.name');
		$definition_slug->explanation = t('widget.capture_form.description');
		$definition_slug->values = $this->publishedCaptureDefinitionOptions();
		$definition_slug->addValidator(new FormValidatorSelected(t('form.validation.required')));
	}

	private function connectionId(): int
	{
		return (int) ($this->getItemId() ?? 0);
	}

	private function isCaptureFormConnection(int $connection_id): bool
	{
		$connection = Widget::getConnectionData($connection_id);

		return is_array($connection)
			&& (string)($connection['widget_name'] ?? '') === $this->captureFormWidgetName();
	}

	private function captureFormWidgetName(): string
	{
		$constant = WidgetList::class . '::CAPTUREFORM';

		return defined($constant) ? (string)constant($constant) : 'CaptureForm';
	}

	/**
	 * @return list<array{inputtype: string, value: string, label: string}>
	 */
	private function publishedCaptureDefinitionOptions(): array
	{
		$options = [];

		foreach ((new FormCaptureAuthoringService())->listDefinitions() as $definition) {
			if (($definition['published_version_id'] ?? null) === null) {
				continue;
			}

			$slug = (string)($definition['definition_slug'] ?? '');

			if ($slug === '') {
				continue;
			}

			$options[] = [
				'inputtype' => 'option',
				'value' => $slug,
				'label' => $slug,
			];
		}

		return $options;
	}
}
