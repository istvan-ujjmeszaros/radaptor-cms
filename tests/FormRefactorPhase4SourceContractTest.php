<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FormRefactorPhase4SourceContractTest extends TestCase
{
	public function testAbstractFormKeepsImperativePathAndAddsDescriptorAdapterPath(): void
	{
		$source = $this->source('modules-common/Form/classes/class.AbstractForm.php');

		$this->assertStringContainsString('public function getDescriptor(): ?array', $source);
		$this->assertStringContainsString('$descriptor = $this->getDescriptor();', $source);
		$this->assertStringContainsString('FormDescriptorAdapter::buildInputs($this, $descriptor);', $source);
		$this->assertStringContainsString('$this->makeInputs();', $source);
	}

	public function testPhase4fPublisherExposesDryRunAndApplyContracts(): void
	{
		$source = $this->source('modules-common/Form/classes/class.FormCaptureDescriptorSpecLoader.php');

		$this->assertStringContainsString('final class FormCaptureDescriptorSpecLoader', $source);
		$this->assertStringContainsString('public static function previewPublish(', $source);
		$this->assertStringContainsString('public static function applyPublish(', $source);
		$this->assertStringContainsString('public static function previewSync(', $source);
		$this->assertStringContainsString('public static function applySync(', $source);
		$this->assertStringContainsString('FormCaptureDescriptorSchemaValidator::validateForDefinition', $source);
		$this->assertStringContainsString('FormCaptureDefinitionRepository', $source);
		$this->assertStringContainsString("'dry_run' => true", $source);
		$this->assertStringContainsString("'dry_run' => false", $source);
	}

	public function testPhase4fRuntimeCacheContractGuardsPublishedDescriptorIntegrity(): void
	{
		$cache_source = $this->source('modules-common/Form/classes/class.FormCaptureCompiledDescriptorCache.php');
		$repository_source = $this->source('modules-common/Form/classes/class.FormCaptureDefinitionRepository.php');

		$this->assertStringContainsString('final class FormCaptureCompiledDescriptorCache', $cache_source);
		$this->assertStringContainsString('public function write(', $cache_source);
		$this->assertStringContainsString('public function read(', $cache_source);
		$this->assertStringContainsString('public function deleteStaleForSlug(', $cache_source);
		$this->assertStringContainsString('descriptor_hash', $cache_source);
		$this->assertStringContainsString('normalized_descriptor_hash', $cache_source);
		$this->assertStringContainsString('Config::LINUX_FILE_OWNER', $cache_source);
		$this->assertStringContainsString('Config::LINUX_FILE_GROUP', $cache_source);
		$this->assertStringContainsString('Config::LINUX_FILE_MODE_DIRECTORY', $cache_source);
		$this->assertStringContainsString('canChangeGroup(', $cache_source);
		$this->assertStringContainsString('FormCaptureCompiledDescriptorCache', $repository_source);
		$this->assertStringContainsString('hash_equals', $repository_source);
		$this->assertStringContainsString('descriptor_hash', $repository_source);
	}

	public function testPhase4fCaptureWidgetKeepsUnavailableDefinitionsAsRenderableFallback(): void
	{
		$source = $this->source('modules-common/Form/widgets/Widget.CaptureForm.php');

		$this->assertStringContainsString('FormDefinitionResolver::resolveForRender($definition_slug', $source);
		$this->assertStringContainsString('catch (FormCaptureRuntimeException)', $source);
		$this->assertStringContainsString("t('form.capture.error_unavailable')", $source);
		$this->assertStringContainsString('$resolution === null || !$resolution->isCapture()', $source);
		$this->assertStringNotContainsString('Kernel::abort', $source);
	}

	public function testPhase4jVersionedSubmitUsesExactCaptureVersionWhenProvided(): void
	{
		$context_source = $this->source('modules-common/Form/classes/class.FormSubmitContext.php');
		$event_source = $this->source('modules-common/Form/events/Event.FormSubmit.php');
		$resolver_source = $this->source('modules-common/Form/classes/class.FormDefinitionResolver.php');
		$repository_source = $this->source('modules-common/Form/classes/class.FormCaptureDefinitionRepository.php');
		$validator_source = $this->source('modules-common/Form/classes/class.FormCaptureDescriptorSchemaValidator.php');

		$this->assertStringContainsString('FIELD_FORM_DEFINITION_VERSION_ID', $context_source);
		$this->assertStringContainsString('FIELD_FORM_RENDER_STATE_ID', $context_source);
		$this->assertStringContainsString('SESSION_KEY_RENDER_STATES', $context_source);
		$this->assertStringContainsString('form_definition_resolution', $context_source);
		$this->assertStringContainsString('formDefinitionVersionId: self::positiveIntOrNull', $context_source);
		$this->assertStringContainsString('validateRenderState(array $post)', $context_source);
		$this->assertStringContainsString('hasRenderStateForSubmittedForm()', $context_source);
		$this->assertStringContainsString('$render_state_error = $context->validateRenderState($post);', $event_source);
		$this->assertStringContainsString('FormDefinitionResolver::resolve($context->formId, $context->formDefinitionVersionId)', $event_source);
		$this->assertLessThan(
			strpos($event_source, 'FormDefinitionResolver::resolve($context->formId, $context->formDefinitionVersionId)'),
			strpos($event_source, '$render_state_error = $context->validateRenderState($post);')
		);
		$this->assertStringContainsString('resolve(string $form_id, ?int $form_definition_version_id = null)', $resolver_source);
		$this->assertStringContainsString('findPublishedResolution($form_id, $form_definition_version_id)', $resolver_source);
		$this->assertStringContainsString('findPublishedResolution(string $definition_slug, ?int $version_id = null)', $repository_source);
		$this->assertStringContainsString("v.version_id = ?", $repository_source);
		$this->assertStringContainsString("v.status = 'published'", $repository_source);
		$this->assertStringContainsString('FormSubmitContext::FIELD_FORM_DEFINITION_VERSION_ID', $validator_source);
		$this->assertStringContainsString('FormSubmitContext::FIELD_FORM_RENDER_STATE_ID', $validator_source);
	}

	public function testPhase4jBuilderAuthoringContractsAreCsrfGuardedAuditedAndDraftOnly(): void
	{
		$authoring_source = $this->source('modules-common/Form/classes/class.FormCaptureAuthoringService.php');
		$create_source = $this->source('modules-common/Form/events/Event.FormBuilderCreate.php');
		$save_source = $this->source('modules-common/Form/events/Event.FormBuilderSaveDraft.php');
		$publish_source = $this->source('modules-common/Form/events/Event.FormBuilderPublish.php');
		$preview_source = $this->source('modules-common/Form/events/Event.FormBuilderPreviewRender.php');
		$fragment_source = $this->source('modules-common/Form/events/Event.FormBuilderEditorFragment.php');
		$load_draft_source = $this->source('modules-common/Form/events/Event.FormBuilderLoadDraftVersion.php');
		$note_source = $this->source('modules-common/Form/events/Event.FormBuilderUpdateDraftNote.php');
		$field_move_source = $this->source('modules-common/Form/events/Event.FormEditorMoveField.php');
		$field_remove_source = $this->source('modules-common/Form/events/Event.FormEditorRemoveField.php');
		$field_publish_source = $this->source('modules-common/Form/events/Event.FormEditorPublish.php');
		$field_update_source = $this->source('modules-common/Form/events/Event.FormEditorUpdateField.php');

		foreach ([$create_source, $save_source, $publish_source, $preview_source, $fragment_source, $load_draft_source, $note_source, $field_move_source, $field_remove_source, $field_publish_source, $field_update_source] as $event_source) {
			$this->assertStringContainsString('FormBuilderEventHelper::authorizeContentAdmin', $event_source);
		}

		foreach ([$create_source, $save_source, $publish_source, $preview_source, $load_draft_source, $note_source] as $event_source) {
			$this->assertStringContainsString('FormBuilderEventHelper::validateCsrfFromPost', $event_source);
		}

		$this->assertStringContainsString("CmsMutationAuditService::withContext(\n\t\t\t\t'form_builder.create'", $create_source);
		$this->assertStringContainsString("CmsMutationAuditService::withContext(\n\t\t\t\t'form_builder.save_draft'", $save_source);
		$this->assertStringContainsString("CmsMutationAuditService::recordLeaf('form_builder.save_draft.conflict'", $save_source);
		$this->assertStringContainsString("CmsMutationAuditService::withContext(\n\t\t\t\t'form_builder.publish'", $publish_source);
		$this->assertStringContainsString("CmsMutationAuditService::withContext(\n\t\t\t\t'form_builder.update_draft_note'", $note_source);
		$this->assertStringContainsString("CmsMutationAuditService::withContext(\n\t\t\t\t'form_editor.move_field'", $field_move_source);
		$this->assertStringContainsString("CmsMutationAuditService::withContext(\n\t\t\t\t'form_editor.remove_field'", $field_remove_source);
		$this->assertStringContainsString("CmsMutationAuditService::withContext(\n\t\t\t\t'form_editor.publish'", $field_publish_source);
		$this->assertStringContainsString("CmsMutationAuditService::withContext(\n\t\t\t\t'form_editor.update_field'", $field_update_source);
		$this->assertStringContainsString('CSRF_INLINE_FORM_COMMAND_FORM_ID', $field_publish_source);
		$this->assertStringContainsString('publishDraft($definition_slug)', $field_publish_source);
		$this->assertStringContainsString('EditModeMutationCommand::replaceForm($widget_connection_id)', $field_publish_source);
		$this->assertStringNotContainsString('CmsMutationAuditService::withContext', $preview_source);
		$this->assertStringNotContainsString('CmsMutationAuditService::withContext', $load_draft_source);
		$this->assertStringNotContainsString('FormBuilderEventHelper::validateCsrfFromPost', $fragment_source);
		$this->assertStringNotContainsString('CmsMutationAuditService::withContext', $fragment_source);
		$this->assertStringContainsString("'status' => self::STATUS_DRAFT", $authoring_source);
		$this->assertStringContainsString('Only the active draft version can be published.', $authoring_source);
		$this->assertStringContainsString('Shipped capture form definitions are read-only in the builder.', $authoring_source);
		$this->assertStringContainsString('status = ?', $authoring_source);
		$this->assertStringContainsString('self::STATUS_ABANDONED', $authoring_source);
	}

	public function testCmsMutationAuditSchemaAlignmentMigrationCoversPublishedShapeChanges(): void
	{
		$migration_source = $this->source('migrations/20260527_121000_align_cms_mutation_audit_schema.php');

		$this->assertStringContainsString("tableExists(\$pdo, 'cms_mutation_audit')", $migration_source);
		$this->assertStringContainsString("SET `actor_type` = 'internal'", $migration_source);
		$this->assertStringContainsString('MODIFY COLUMN `cms_mutation_audit_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', $migration_source);
		$this->assertStringContainsString('MODIFY COLUMN `phase` VARCHAR(64) NOT NULL', $migration_source);
		$this->assertStringContainsString('MODIFY COLUMN `actor_type` VARCHAR(32) NOT NULL', $migration_source);
		$this->assertStringContainsString('MODIFY COLUMN `actor_user_id` BIGINT UNSIGNED NULL', $migration_source);
		$this->assertStringContainsString('MODIFY COLUMN `resource_id` BIGINT UNSIGNED NULL', $migration_source);
		$this->assertStringContainsString('MODIFY COLUMN `page_id` BIGINT UNSIGNED NULL', $migration_source);
		$this->assertStringContainsString('MODIFY COLUMN `widget_connection_id` BIGINT UNSIGNED NULL', $migration_source);
		$this->assertStringContainsString("MODIFY COLUMN `result_status` VARCHAR(64) NOT NULL DEFAULT 'success'", $migration_source);
		$this->assertStringContainsString('MODIFY COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', $migration_source);
	}

	public function testPhase4jBuilderWidgetAndPhpstanCoverageIncludesTouchedRuntimeFiles(): void
	{
		$authoring_source = $this->source('modules-common/Form/classes/class.FormCaptureAuthoringService.php');
		$widget_source = $this->source('modules-common/Form/widgets/Widget.CaptureFormBuilder.php');
		$list_widget_source = $this->source('modules-common/Form/widgets/Widget.CaptureFormList.php');
		$template_source = $this->source('modules-common/Form/templates/template.captureFormBuilder.php');
		$field_properties_template_source = $this->source('modules-common/Form/templates/template.captureFieldProperties.php');
		$field_wrapper_template_source = $this->source('modules-common/Form/templates/template.formEditorField.php');
		$list_template_source = $this->source('modules-common/Form/templates/template.captureFormList.php');
		$so_admin_form_template_source = $this->source('templates-common/default-SoAdmin/Form/template.sdui.form.php');
		$phpstan_source = $this->source('phpstan.neon');

		$this->assertStringContainsString("library('__ADMIN_FORM_BUILDER')", $template_source);
		$this->assertStringContainsString("library('__ADMIN_FORM_BUILDER')", $list_template_source);
		$this->assertStringContainsString('FormSubmitContext::issueCsrfTokenForForm(FormBuilderEventHelper::CSRF_FORM_ID)', $authoring_source);
		$this->assertStringContainsString('FormSubmitContext::issueCsrfTokenForForm(FormBuilderEventHelper::CSRF_FORM_ID)', $list_widget_source);
		$this->assertStringContainsString('new FormCaptureFieldPropertyProvider()', $template_source);
		$this->assertStringContainsString('FormCaptureFieldPropertyProvider::MODE_BUILDER', $template_source);
		$this->assertStringContainsString('FormCaptureFieldPropertyProvider::MODE_EDITMODE', $field_properties_template_source);
		$this->assertStringContainsString('data-form-editor-field-toggle', $field_wrapper_template_source);
		$this->assertStringContainsString('data-form-editor-field-uid', $field_wrapper_template_source);
		$this->assertStringContainsString('data-form-editor-field-panel', $field_properties_template_source);
		$this->assertStringContainsString('hx-post="<?= e($action) ?>"', $field_properties_template_source);
		$this->assertStringContainsString('name="field_uid"', $field_properties_template_source);
		$this->assertStringContainsString('data-form-editor-form-target', $so_admin_form_template_source);
		$form_close_position = strpos($so_admin_form_template_source, '</form>');
		$post_form_chrome_position = strpos($so_admin_form_template_source, "\$this->fetchContent('post_form_chrome')");
		$this->assertIsInt($form_close_position);
		$this->assertIsInt($post_form_chrome_position);
		$this->assertGreaterThan($form_close_position, $post_form_chrome_position);
		$this->assertStringContainsString('form.builder.error_create', $widget_source);
		$this->assertStringContainsString("Url::getUrl('form_builder.editor_fragment')", $list_widget_source);
		$this->assertStringContainsString('data-form-list-editor-fragment-url-value', $list_template_source);
		$this->assertStringContainsString('\'i18n_workbench_url\' => $this->i18nWorkbenchUrl()', $authoring_source);
		$this->assertStringContainsString('\'i18n_workbench_url\' => (string)($state[\'i18n_workbench_url\'] ?? \'\')', $template_source);
		$this->assertStringContainsString('$showLifecycleColumns = $sourceFilter !== \'system\';', $list_template_source);
		$this->assertStringContainsString('<?php if (!$readOnly): ?>', $template_source);
		$this->assertStringNotContainsString('return_to', $list_template_source);
		$this->assertStringContainsString("'form' => \$definitionSlug", $list_template_source);
		$this->assertStringNotContainsString("'edit' => \$definitionSlug", $list_template_source);
		$this->assertStringNotContainsString('?edit=', $list_template_source);
		$this->assertStringContainsString('nav nav-tabs form-list__tabs', $list_template_source);
		$this->assertStringContainsString('nav-link form-list__tab', $list_template_source);
		$this->assertStringContainsString('aria-current="page"', $list_template_source);
		$this->assertStringContainsString("'/admin/forms/'", $list_widget_source);

		foreach ([
			'modules-common/Form/classes/class.FormSubmitContext.php',
			'modules-common/Form/classes/class.FormCaptureFieldEditorCommandProvider.php',
			'modules-common/Form/classes/class.FormCaptureFieldIdentity.php',
			'modules-common/Form/classes/class.FormCaptureFieldPropertyProvider.php',
			'modules-common/Form/classes/class.FormEditorFieldCommand.php',
			'modules-common/Form/classes/class.FormEditorFieldCommandContext.php',
			'modules-common/Form/classes/class.FormDefinitionResolver.php',
			'modules-common/Form/classes/class.FormDefinitionResolution.php',
			'modules-common/Form/classes/class.FormResponseEmitter.php',
			'modules-common/Cms/classes/class.EditorInsertItem.php',
			'modules-common/Cms/classes/class.EditorInsertSurfaceBuilder.php',
			'modules-common/Cms/classes/class.EditModeMutationCommand.php',
			'modules-common/Cms/classes/class.EditModeMutationResponder.php',
			'modules-common/Cms/classes/class.WidgetEditCommand.php',
			'modules-common/Cms/classes/class.CmsFragmentRenderer.php',
			'modules-common/Form/interfaces/interface.iFormEditorFieldCommandProvider.php',
			'modules-common/Form/events/Event.FormSubmit.php',
			'modules-common/Form/events/Event.FormEditorInsertField.php',
			'modules-common/Form/events/Event.FormEditorMoveField.php',
			'modules-common/Form/events/Event.FormEditorRemoveField.php',
			'modules-common/Form/events/Event.FormEditorPublish.php',
			'modules-common/Form/events/Event.FormEditorUpdateField.php',
			'modules-common/Cms/events/Event.WidgetConnectionAdd.php',
			'modules-common/Cms/events/Event.WidgetConnectionRemove.php',
			'modules-common/Cms/events/Event.WidgetConnectionSwap.php',
			'modules-common/Form/events/Event.FormBuilderEditorFragment.php',
			'modules-common/Form/events/Event.FormBuilderLoadDraftVersion.php',
			'modules-common/Form/events/Event.FormBuilderUpdateDraftNote.php',
			'modules-common/Form/widgets/Widget.CaptureForm.php',
			'modules-common/Form/classes/class.I18nReferenceAuditService.php',
			'modules-common/Form/widgets/Widget.CaptureFormBuilder.php',
			'modules-common/Form/widgets/Widget.CaptureFormList.php',
		] as $path) {
			$this->assertStringContainsString($path, $phpstan_source);
		}
	}

	public function testEditModeMutationsUseSharedCommandResponderAndStableFieldIds(): void
	{
		$field_identity_source = $this->source('modules-common/Form/classes/class.FormCaptureFieldIdentity.php');
		$validator_source = $this->source('modules-common/Form/classes/class.FormCaptureDescriptorSchemaValidator.php');
		$abstract_form_source = $this->source('modules-common/Form/classes/class.AbstractForm.php');
		$authoring_source = $this->source('modules-common/Form/classes/class.FormCaptureAuthoringService.php');
		$mutation_command_source = $this->source('modules-common/Cms/classes/class.EditModeMutationCommand.php');
		$responder_source = $this->source('modules-common/Cms/classes/class.EditModeMutationResponder.php');
		$fragment_source = $this->source('modules-common/Cms/classes/class.CmsFragmentRenderer.php');
		$tree_builder_source = $this->source('modules-common/Cms/classes/class.WebpageTreeBuilder.php');
		$field_command_provider_source = $this->source('modules-common/Form/classes/class.FormCaptureFieldEditorCommandProvider.php');
		$field_template_source = $this->source('modules-common/Form/templates/template.formEditorField.php');
		$insert_event_source = $this->source('modules-common/Form/events/Event.FormEditorInsertField.php');
		$move_event_source = $this->source('modules-common/Form/events/Event.FormEditorMoveField.php');
		$remove_event_source = $this->source('modules-common/Form/events/Event.FormEditorRemoveField.php');
		$publish_event_source = $this->source('modules-common/Form/events/Event.FormEditorPublish.php');
		$update_event_source = $this->source('modules-common/Form/events/Event.FormEditorUpdateField.php');
		$widget_add_source = $this->source('modules-common/Cms/events/Event.WidgetConnectionAdd.php');
		$widget_remove_source = $this->source('modules-common/Cms/events/Event.WidgetConnectionRemove.php');
		$widget_swap_source = $this->source('modules-common/Cms/events/Event.WidgetConnectionSwap.php');
		$capture_widget_source = $this->source('modules-common/Form/widgets/Widget.CaptureForm.php');
		$widget_command_source = $this->source('modules-common/Cms/classes/class.WidgetEditCommand.php');
		$widget_source = $this->source('modules-common/Cms/classes/class.Widget.php');
		$editor_insert_source = $this->source('templates-common/default-SoAdmin/Cms/template.editorInsert.php');
		$edit_bar_source = $this->source('templates-common/default-SoAdmin/Cms/template.editBar.common.php');

		$this->assertStringContainsString("public const string DESCRIPTOR_KEY = 'editor_uid';", $field_identity_source);
		$this->assertStringContainsString('ensureDescriptorFieldUids($normalized)', $validator_source);
		$this->assertStringContainsString('FormCaptureFieldIdentity::formTargetId($this->editableWidgetConnectionId())', $abstract_form_source);
		$this->assertStringContainsString('FormCaptureFieldIdentity::fieldTargetId($widget_connection_id, $field_uid)', $abstract_form_source);
		$this->assertStringContainsString('buildPublishCommand(WidgetConnection $connection)', $capture_widget_source);
		$this->assertStringContainsString("Url::getUrl('form_editor.publish')", $capture_widget_source);
		$this->assertStringContainsString('CSRF_INLINE_FORM_COMMAND_FORM_ID', $capture_widget_source);
		$this->assertStringContainsString("public string \$method = 'get'", $widget_command_source);
		$this->assertStringContainsString('public array $payload = []', $widget_command_source);
		$this->assertStringContainsString("public const string TARGET_WIDGET_ELEMENT = 'widget_element';", $mutation_command_source);
		$this->assertStringContainsString('replaceWidgetToolbar(int $widget_connection_id)', $mutation_command_source);
		$this->assertStringContainsString("'method' => strtolower(\$command->method)", $widget_source);
		$this->assertStringContainsString('hx-post', $edit_bar_source);
		$this->assertStringContainsString('hx-vals', $edit_bar_source);
		$this->assertStringContainsString('FormCaptureFieldIdentity::generateUid($this->existingFieldUids($descriptor))', $authoring_source);
		$this->assertStringContainsString('renderElementTargetsFromWidget', $fragment_source);
		$this->assertStringContainsString('if ($targets === []) {', $fragment_source);
		$this->assertStringContainsString("if (\$type === 'component')", $fragment_source);
		$this->assertStringContainsString('return $this->view->isEditable() || $this->view->getLayoutType() instanceof iPartialNavigableLayout;', $tree_builder_source);
		$this->assertStringContainsString('HX-Trigger', $responder_source);
		$this->assertStringContainsString('EditModeMutationCommand::TARGET_WIDGET_ELEMENT', $responder_source);
		$this->assertStringContainsString('Widget element mutation command is missing widget_connection_id.', $responder_source);
		$this->assertStringContainsString('implements iFormEditorFieldCommandProvider', $field_command_provider_source);
		$this->assertStringContainsString("Url::getUrl('form_editor.move_field')", $field_command_provider_source);
		$this->assertStringContainsString("Url::getUrl('form_editor.remove_field')", $field_command_provider_source);
		$this->assertStringContainsString("'commands' => array_map", $abstract_form_source);
		$this->assertStringContainsString('data-edit-mode-command', $field_template_source);
		$this->assertStringContainsString('data-edit-mode-confirm', $field_template_source);
		$this->assertStringContainsString('EditModeMutationCommand::replaceForm($widget_connection_id, $reveal_target_id)', $insert_event_source);
		$this->assertStringContainsString('EditModeMutationCommand::replaceForm($widget_connection_id, $reveal_target_id)', $move_event_source);
		$this->assertStringContainsString('EditModeMutationCommand::replaceForm($widget_connection_id)', $remove_event_source);
		$this->assertStringContainsString('EditModeMutationCommand::replaceForm($widget_connection_id)', $publish_event_source);
		$this->assertStringContainsString('EditModeMutationCommand::replaceFormField($widget_connection_id, $field_uid)', $update_event_source);

		foreach ([$insert_event_source, $move_event_source, $remove_event_source, $publish_event_source, $update_event_source] as $event_source) {
			$this->assertStringContainsString('EditModeMutationCommand::replaceWidgetToolbar($widget_connection_id)', $event_source);
		}
		$this->assertStringContainsString('EditModeMutationCommand::replaceSlot($slot_name, \'edit-widget-\' . (int)$connection_id)', $widget_add_source);
		$this->assertStringContainsString('EditModeMutationCommand::replaceSlot($slot_name)', $widget_remove_source);
		$this->assertStringContainsString('array_values($commands)', $widget_swap_source);
		$this->assertStringContainsString("\$this->registerLibrary('__ADMIN_EDIT_MODE');", $editor_insert_source);
		$this->assertStringContainsString("\$edit_mode_hx_swap = 'none show:none focus-scroll:false';", $editor_insert_source);
		$this->assertStringContainsString("\$edit_mode_hx_swap = 'none show:none focus-scroll:false';", $edit_bar_source);
		$this->assertStringContainsString('hx-vals="<?= e($hx_values) ?>"', $editor_insert_source);
		$this->assertStringContainsString('hx-params="<?= e($item_payload_name) ?>"', $editor_insert_source);
		$this->assertStringContainsString('hx-get="<?= event_url(\'widgetConnection.remove\'', $edit_bar_source);
		$this->assertStringContainsString('data-edit-mode-command', $edit_bar_source);
		$this->assertStringContainsString('data-edit-mode-confirm', $edit_bar_source);
	}

	private function source(string $relativePath): string
	{
		$path = dirname(__DIR__) . '/' . $relativePath;
		$this->assertFileExists($path);

		return (string) file_get_contents($path);
	}
}
