<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FormRefactorPhase2SourceContractTest extends TestCase
{
	public function testAbstractFormUsesExplicitProcessResultInsteadOfConstructorSubmitSideEffects(): void
	{
		$source = $this->source('modules-common/Form/classes/class.AbstractForm.php');

		$this->assertStringContainsString('public function process(', $source);
		$this->assertStringContainsString(': FormResult', $source);
		$this->assertStringContainsString('$this->bind($payload', $source);
		$this->assertStringContainsString('FormResult::success', $source);
		$this->assertStringContainsString('FormResult::invalid', $source);
		$this->assertStringContainsString('FormResult::denied', $source);
		$this->assertStringNotContainsString('_processForm', $source);
		$this->assertStringNotContainsString('<!--\' . self::_SUBMIT_VALUE_SAVE', $source);
		$this->assertStringNotContainsString('SystemMessages::_error($error', $source);
	}

	public function testDedicatedSubmitEndpointOwnsSubmitContextAndResponseEmission(): void
	{
		$source = $this->source('modules-common/Form/events/Event.FormSubmit.php');

		$this->assertStringContainsString('class EventFormSubmit', $source);
		$this->assertStringContainsString('FormSubmitContext::fromPost($post)', $source);
		$this->assertStringContainsString('$context->isCurrentBuild()', $source);
		$this->assertStringContainsString('$context->canAccessHostContext()', $source);
		$this->assertStringContainsString('FormDefinitionResolver::resolve($context->formId', $source);
		$this->assertStringContainsString("BrowserEventDocumentationHelper::param('form_id'", $source);
		$this->assertStringContainsString("BrowserEventDocumentationHelper::param('form_instance_id'", $source);
		$this->assertStringContainsString('(new FormResponseEmitter())->emit', $source);
		$this->assertStringContainsString('Request::wantsNonHtmlResponse()', $source);
		$this->assertStringNotContainsString("'form_id' => 'Class-backed form descriptor id.'", $source);
	}

	public function testStableFieldKeysArePartOfTheTreeAndBindingContract(): void
	{
		$input_source = $this->source('modules-common/Form/classes/class.FormInput.php');
		$form_source = $this->source('modules-common/Form/classes/class.AbstractForm.php');
		$template_source = $this->source('templates-common/default-SoAdmin/Form/template.sdui.form.input.text.php');

		$this->assertStringContainsString('public ?string $key = null', $input_source);
		$this->assertStringContainsString('public function getKey(): string', $input_source);
		$this->assertStringContainsString('public function bindSubmittedValue', $input_source);
		$this->assertStringContainsString("'field_key' => \$this->getKey()", $input_source);
		$this->assertStringContainsString("'data_field_key' => \$this->getKey()", $input_source);
		$this->assertStringContainsString("'key' => \$input->getKey()", $form_source);
		$this->assertStringContainsString('data-field-key', $template_source);
	}

	public function testFormRenderTreeBuildersUseContentsAsPrimaryTreeKey(): void
	{
		$form_source = $this->source('modules-common/Form/classes/class.AbstractForm.php');
		$capture_form_source = $this->source('modules-common/Form/classes/class.CaptureForm.php');
		$sdui_node_source = $this->source('modules-common/Cms/classes/class.SduiNode.php');

		$this->assertStringContainsString('SduiNode::create(', $form_source);
		$this->assertStringNotContainsString("'slots' =>", $form_source);
		$this->assertStringContainsString("\$tree['contents']['hidden_fields'][] = \$honeypot;", $capture_form_source);
		$this->assertStringContainsString('return SduiNode::normalize($tree);', $capture_form_source);
		$this->assertStringNotContainsString("\$tree['slots']", $capture_form_source);
		$this->assertStringContainsString("'slots'     => \$contents", $sdui_node_source);
		$this->assertStringContainsString("is_array(\$node['contents'] ?? null) ? \$node['contents']", $sdui_node_source);
	}

	public function testTemplateSubmitAndAutocompleteCompatibilityContracts(): void
	{
		$form_template_source = $this->source('templates-common/default-SoAdmin/Form/template.sdui.form.php');
		$text_template_source = $this->source('templates-common/default-SoAdmin/Form/template.sdui.form.input.text.php');

		$this->assertStringContainsString('$form_descriptor_id = $form_id;', $form_template_source);
		$this->assertStringContainsString('document.getElementById(connectedAutocompleteInputId)', $text_template_source);
		$this->assertStringContainsString('$(source).closest("form").find("[data-field-key]")', $text_template_source);
		$this->assertStringNotContainsString('$(' . "'[data-field-key=", $text_template_source);
	}

	public function testClassBasedResolverReplacesHardcodedFormTypeLookupInRuntimePath(): void
	{
		$form_source = $this->source('modules-common/Form/classes/class.Form.php');
		$widget_source = $this->source('modules-common/Form/widgets/Widget.Form.php');

		$this->assertStringContainsString('FormDefinitionResolver::requireResolution($form_type)', $form_source);
		$this->assertStringContainsString('FormClassResolver::resolveClassName', $widget_source);
		$this->assertStringNotContainsString("'FormType' . ucwords", $form_source);
		$this->assertStringNotContainsString("'FormType' . ucwords", $widget_source);
	}

	private function source(string $relativePath): string
	{
		$path = dirname(__DIR__) . '/' . $relativePath;
		$this->assertFileExists($path);

		return (string) file_get_contents($path);
	}
}
