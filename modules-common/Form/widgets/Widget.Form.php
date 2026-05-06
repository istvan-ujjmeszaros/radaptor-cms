<?php

class WidgetForm extends AbstractWidget implements iMockable
{
	public const string ID = 'form';

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

	public function hasRole(): bool
	{
		return Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN);
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/',
			'resource_name' => 'form.html',
			'layout' => 'admin_default',
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		if (Request::_GET('form_id')) {
			$form_type = Request::_GET('form_id');
		} elseif ($connection->getExtraparam('form_id')) {
			$form_type = $connection->getExtraparam('form_id');
		} else {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => t('cms.form.no_id'),
			]);
		}

		$formClassName = 'FormType' . ucwords($form_type);

		// Check required URL params before instantiating the form
		$missing = Request::getMissingParams($formClassName::getRequiredUrlParams());

		if (!empty($missing)) {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'title' => t('common.missing_required_url_params'),
				'missing' => $missing,
			]);
		}

		$form_id = md5($form_type . '_' . $connection->connection_id);

		$form = Form::factory($form_type, $form_id, $tree_build_context);

		if ($form->hasRole()) {
			return $form->buildTree();
		} else {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => $this->getAccessDeniedMessage(),
			]);
		}
	}

	public function buildMockTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		return SduiNode::create(
			component: 'form',
			props: [
				'form_id' => 'preview_form_widget',
				'form_name' => 'preview_demo',
				'mode' => AbstractForm::_MODE_CREATE,
				'action' => '',
				'method' => 'post',
				'form_class' => 'preview-form-widget',
				'title' => 'Form preview demo',
				'sub_title' => 'Rendered from the canonical component tree in both HTML and JSON preview modes.',
				'autocomplete' => true,
				'focusable' => false,
				'button_save' => [
					'text' => 'Save changes',
					'icon' => IconNames::FORM_SAVE,
					'class' => '',
				],
				'button_cancel' => [
					'text' => 'Cancel',
					'icon' => IconNames::FORM_CANCEL,
					'class' => '',
				],
				'post_javascript_file' => '',
				'field_refs' => [
					'title' => [
						'id' => 'preview_form_widget_title',
						'row_id' => 'row_preview_form_widget_title',
					],
					'password' => [
						'id' => 'preview_form_widget_password',
						'row_id' => 'row_preview_form_widget_title',
					],
					'type' => [
						'id' => 'preview_form_widget_type',
						'row_id' => 'row_preview_form_widget_classification',
					],
					'date' => [
						'id' => 'preview_form_widget_date',
						'row_id' => 'row_preview_form_widget_classification',
					],
					'datetime' => [
						'id' => 'preview_form_widget_datetime',
						'row_id' => 'row_preview_form_widget_schedule',
					],
					'featured' => [
						'id' => 'preview_form_widget_featured',
						'row_id' => 'row_preview_form_widget_schedule',
					],
					'visibility' => [
						'id' => 'preview_form_widget_visibility',
						'row_id' => 'row_preview_form_widget_visibility',
					],
					'delivery' => [
						'id' => 'preview_form_widget_delivery',
						'row_id' => 'row_preview_form_widget_visibility',
					],
					'notes' => [
						'id' => 'preview_form_widget_notes',
						'row_id' => 'row_preview_form_widget_notes',
					],
				],
			],
			contents: [
				'hidden_fields' => [
					$this->buildPreviewInputTree(
						'form.input.hidden',
						[
							'id' => 'preview_form_widget_token',
							'name' => 'csrf_token',
							'value' => 'preview-token',
						]
					),
				],
				'rows' => [
					$this->buildPreviewRowTree(
						'row_preview_form_widget_title',
						[
							$this->buildPreviewInputTree('form.input.text', [
								'id' => 'preview_form_widget_title',
								'name' => 'title',
								'label' => 'Title',
								'value' => 'Quarterly platform review',
								'errors' => [],
								'input_style_attr' => '',
								'readonly' => false,
							], 'Use a short label that business users immediately recognize.'),
							$this->buildPreviewInputTree('form.input.password', [
								'id' => 'preview_form_widget_password',
								'name' => 'password',
								'label' => 'Password',
								'value' => '',
								'errors' => [],
								'input_style_attr' => '',
							], 'Password fields stay masked in both HTML and JSON preview modes.'),
						]
					),
					$this->buildPreviewRowTree(
						'row_preview_form_widget_classification',
						[
							$this->buildPreviewInputTree('form.input.select', [
								'id' => 'preview_form_widget_type',
								'name' => 'type',
								'label' => 'Type',
								'value' => 'review',
								'errors' => [],
								'input_style_attr' => '',
								'required' => true,
								'placeholder_label' => 'Choose an option',
								'values' => [
									['inputtype' => 'option', 'value' => 'review', 'label' => 'Review'],
									['inputtype' => 'option', 'value' => 'request', 'label' => 'Request'],
									['inputtype' => 'option', 'value' => 'incident', 'label' => 'Incident'],
								],
							]),
							$this->buildPreviewInputTree('form.input.date', [
								'id' => 'preview_form_widget_date',
								'name' => 'target_date',
								'label' => 'Target date',
								'value' => '2026-03-31',
								'errors' => [],
								'input_style_attr' => '',
								'readonly' => false,
							]),
						]
					),
					$this->buildPreviewRowTree(
						'row_preview_form_widget_schedule',
						[
							$this->buildPreviewInputTree('form.input.datetime', [
								'id' => 'preview_form_widget_datetime',
								'name' => 'review_at',
								'label' => 'Review at',
								'value' => '2026-03-31T14:30',
								'errors' => [],
								'input_style_attr' => '',
								'readonly' => false,
							]),
							$this->buildPreviewInputTree('form.input.checkbox', [
								'id' => 'preview_form_widget_featured',
								'name' => 'featured',
								'label' => 'Featured item',
								'checked' => true,
								'errors' => [],
							], 'Single checkbox inputs stay explicit in the structural tree.'),
						]
					),
					$this->buildPreviewRowTree(
						'row_preview_form_widget_visibility',
						[
							$this->buildPreviewInputTree('form.input.checkboxgroup', [
								'id' => 'preview_form_widget_visibility',
								'name' => 'visibility',
								'label' => 'Visibility',
								'value' => [
									'internal' => 1,
									'customer' => 1,
								],
								'values' => [
									'internal' => 'Internal teams',
									'customer' => 'Customer portal',
									'partner' => 'Partner network',
								],
								'errors' => [],
							], 'Checkbox groups demonstrate multiple simultaneous selections.'),
							$this->buildPreviewInputTree('form.input.radiogroup', [
								'id' => 'preview_form_widget_delivery',
								'name' => 'delivery_mode',
								'label' => 'Delivery mode',
								'value' => 'sync',
								'values' => [
									'Synchronous' => 'sync',
									'Asynchronous' => 'async',
									'Hybrid' => 'hybrid',
								],
								'errors' => [],
							], 'Radio groups keep a single selected value.'),
						]
					),
					$this->buildPreviewRowTree(
						'row_preview_form_widget_notes',
						[
							$this->buildPreviewInputTree('form.input.textarea', [
								'id' => 'preview_form_widget_notes',
								'name' => 'notes',
								'label' => 'Notes',
								'value' => 'The JSON preview should be able to render structured form fields without server-side HTML helpers.',
								'errors' => [],
								'input_style_attr' => '',
								'readonly' => false,
							], 'This field demonstrates helper text and multiline content.'),
						]
					),
				],
			],
			type: SduiNode::TYPE_WIDGET,
		);
	}

	/**
	 * @param list<array<string, mixed>> $content
	 * @return array<string, mixed>
	 */
	private function buildPreviewRowTree(string $row_id, array $content): array
	{
		return SduiNode::create(
			component: 'form.row',
			props: [
				'row_id' => $row_id,
			],
			contents: [
				'content' => $content,
			],
			type: SduiNode::TYPE_SUB,
		);
	}

	/**
	 * @param array<string, mixed> $props
	 * @return array<string, mixed>
	 */
	private function buildPreviewInputTree(string $component, array $props, string $info_string = ''): array
	{
		$contents = [];

		if ($component !== 'form.input.hidden') {
			$contents['helper'] = [
				SduiNode::create(
					component: 'form.helper',
					props: [
						'target' => $props['id'] ?? '',
						'error_string' => '',
						'info_string' => $info_string,
					],
				),
			];
		}

		return SduiNode::create(
			component: $component,
			props: $props,
			contents: $contents,
			type: SduiNode::TYPE_SUB,
		);
	}

	public function getEditableCommands(WidgetConnection $connection): array
	{
		$settings = new WidgetEditCommand();
		$settings->title = t('form.form_settings.title');
		$settings->url = Form::getSeoUrl(FormList::FORMSETTINGS, $connection->connection_id);

		$settings->icon = IconNames::CHOOSE;

		$return = [];

		if (Roles::hasRole(RoleList::ROLE_CONTENT_ADMIN)) {
			$return[] = $settings;
		}

		return $return;
	}
	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return true;
	}
}
