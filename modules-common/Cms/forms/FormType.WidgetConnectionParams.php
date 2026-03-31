<?php

class FormTypeWidgetConnectionParams extends AbstractForm
{
	public const string ID = 'widget_connection_params';

	public static function getName(): string
	{
		return t('form.' . self::ID . '.name');
	}

	public static function getDescription(): string
	{
		return t('form.' . self::ID . '.description');
	}

	public static function getListVisibility(): bool
	{
		return Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER);
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/resources/',
			'resource_name' => 'widget-connection-params.html',
			'layout' => 'admin_default',
		];
	}

	public static function getRequiredUrlParams(): array
	{
		return [
			'item_id' => t('cms.widget_connection_params.missing_widget_id'),
		];
	}

	public function hasRole(): bool
	{
		return Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER);
	}

	public function commit(): void
	{
		if (is_null($this->getItemId())) {
			SystemMessages::_error(t('cms.widget_connection_params.no_content_id'));
		} elseif (AttributeHandler::addAttribute(new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, (string) $this->getItemId()), $this->savedata)) {
			SystemMessages::addSystemMessage(t('common.saved'));
		} else {
			SystemMessages::addSystemMessage(t('common.no_changes'));
		}
	}

	public function setMetadata(): void
	{
		$this->_meta->title = t('cms.widget_connection_params.title');
		$this->_meta->sub_title = t('cms.widget_connection_params.subtitle', [
			'page' => WidgetConnection::getOwnerWebpageTitle($this->getItemId()),
		]);
	}

	public function setInitValues(): void
	{
		$this->initvalues = AttributeHandler::getAttributes(new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, (string) $this->getItemId()));
	}

	public function makeInputs(): void
	{
		$name = new FormInputText('anchor', $this);
		$name->label = t('cms.widget_connection_params.field.anchor.label');
		$name->explanation = t('cms.widget_connection_params.field.anchor.explanation');

		$margin_top = new FormInputText('margin-top', $this);
		$margin_top->label = t('cms.widget_connection_params.field.margin_top.label');
		$margin_top->explanation = t('cms.widget_connection_params.field.margin_top.explanation');
		$v = new FormValidatorStringlength(t('form.validation.max_length', ['max' => 255]));
		$v->min = 0;
		$v->max = 255;
		$margin_top->addValidator($v);

		$margin_bottom = new FormInputText('margin-bottom', $this);
		$margin_bottom->label = t('cms.widget_connection_params.field.margin_bottom.label');
		$margin_bottom->explanation = t('cms.widget_connection_params.field.margin_bottom.explanation');
		$v = new FormValidatorStringlength(t('form.validation.max_length', ['max' => 255]));
		$v->min = 0;
		$v->max = 255;
		$margin_bottom->addValidator($v);

		$margin_left = new FormInputText('margin-left', $this);
		$margin_left->label = t('cms.widget_connection_params.field.margin_left.label');
		$margin_left->explanation = t('cms.widget_connection_params.field.margin_left.explanation');
		$v = new FormValidatorStringlength(t('form.validation.max_length', ['max' => 255]));
		$v->min = 0;
		$v->max = 255;
		$margin_left->addValidator($v);

		$margin_right = new FormInputText('margin-right', $this);
		$margin_right->label = t('cms.widget_connection_params.field.margin_right.label');
		$margin_right->explanation = t('cms.widget_connection_params.field.margin_right.explanation');
		$v = new FormValidatorStringlength(t('form.validation.max_length', ['max' => 255]));
		$v->min = 0;
		$v->max = 255;
		$margin_right->addValidator($v);

		$width = new FormInputText('width', $this);
		$width->label = t('cms.widget_connection_params.field.width.label');
		$width->explanation = t('cms.widget_connection_params.field.width.explanation');
		$v = new FormValidatorStringlength(t('form.validation.max_length', ['max' => 255]));
		$v->min = 0;
		$v->max = 255;
		$width->addValidator($v);

		$height = new FormInputText('height', $this);
		$height->label = t('cms.widget_connection_params.field.height.label');
		$height->explanation = t('cms.widget_connection_params.field.height.explanation');
		$v = new FormValidatorStringlength(t('form.validation.max_length', ['max' => 255]));
		$v->min = 0;
		$v->max = 255;
		$height->addValidator($v);

		$float = new FormInputSelect('float', $this);
		$float->label = t('cms.widget_connection_params.field.float.label');
		$float->required = false;
		$float->explanation = t('cms.widget_connection_params.field.float.explanation');
		$float->values = [
			[
				'inputtype' => 'option',
				'value' => '',
				'label' => t('cms.widget_connection_params.field.float.none'),
			],
			[
				'inputtype' => 'option',
				'value' => 'clear',
				'label' => t('cms.widget_connection_params.field.float.clear'),
			],
			[
				'inputtype' => 'option',
				'value' => 'left',
				'label' => t('cms.widget_connection_params.field.float.left'),
			],
			[
				'inputtype' => 'option',
				'value' => 'left-clear',
				'label' => t('cms.widget_connection_params.field.float.left_clear'),
			],
			[
				'inputtype' => 'option',
				'value' => 'right',
				'label' => t('cms.widget_connection_params.field.float.right'),
			],
			[
				'inputtype' => 'option',
				'value' => 'right-clear',
				'label' => t('cms.widget_connection_params.field.float.right_clear'),
			],
		];
	}
}
