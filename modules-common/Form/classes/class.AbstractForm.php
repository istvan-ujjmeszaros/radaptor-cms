<?php

abstract class AbstractForm implements iForm, iListable
{
	public const string _MODE_CREATE = 'insert';
	public const string _MODE_UPDATE = 'update';
	public const string _SUBMIT_VALUE_CANCEL = 'cancel';
	public const string _SUBMIT_VALUE_SAVE = 'save';

	public array $savedata = [];
	public array $initvalues = [];
	protected string $_form_id;
	protected string $_form_instance_id;
	protected string $_mode;
	private int $_inputCounter = 0;

	/** @var FormInput[] */
	protected array $_form_inputs;
	protected ?int $_item_id;

	protected FormMetadata $_meta;

	private string $referer;
	private array $_render_context = [];

	/**
	 * URL parameters required for this form to function.
	 * Override in subclasses that need specific params.
	 *
	 * @return array<string, string> Parameter names => descriptions
	 */
	public static function getRequiredUrlParams(): array
	{
		return [];
	}

	public function setInitValues(): void
	{
	}

	public function addInput(FormInput $input): void
	{
		$this->_form_inputs[$input->fieldname] = $input;
	}

	public function getNextInputId(): int
	{
		return ++$this->_inputCounter;
	}

	protected ?iWebpageComposer $_webpage_composer;

	/**
	 * @param array<string, mixed> $render_context
	 */
	public function __construct(protected string $_form_type, string $form_id, protected iTreeBuildContext $_tree_build_context, ?string $referer = null, array $render_context = [])
	{
		$this->_form_instance_id = $form_id;
		$this->_form_id = 'f' . $form_id;
		$this->_form_inputs = [];
		$this->_render_context = $render_context;
		$this->_meta = new FormMetadata();
		$this->_webpage_composer = $this->_tree_build_context instanceof iWebpageComposer ? $this->_tree_build_context : null;

		$item_id = $render_context['item_id'] ?? Request::_GET('item_id');

		if ($item_id) {
			$this->_mode = self::_MODE_UPDATE;
			$this->_item_id = (int)$item_id;
		} else {
			$this->_mode = self::_MODE_CREATE;
			$this->_item_id = null;
		}

		$this->setMetadata();

		if (!is_null($referer)) {
			$this->referer = Url::sanitizeRefererUrl(urldecode($referer));
		} elseif (Request::_GET('referer', false)) {
			$this->referer = Url::sanitizeRefererUrl((string) Request::_GET('referer'));
		} else {
			$this->referer = Url::sanitizeRefererUrl((string) Kernel::getReferer());

			if ($this->_meta->enableAutoReferer && !Request::isHtmxRequest() && (Kernel::getReferer() != '') && !Url::CurrentEqualsToReferer()) {
				Url::redirect(Url::modifyCurrentUrl(['referer' => Url::sanitizeRefererUrl((string) Kernel::getReferer())]));
			}
		}

		if ($this->_mode == self::_MODE_UPDATE) {
			$this->setInitValues();
		}

		$this->makeInputs();

		if (!current($this->_form_inputs) instanceof FormInput) {
			Kernel::abort('makeInputs() must return array of FormInput elements! (' . $this->_form_type . ')');
		}

		$this->applySubmittedRenderState();
	}

	public function getTreeBuildContext(): iTreeBuildContext
	{
		return $this->_tree_build_context;
	}

	public function getWebpageComposer(): ?iWebpageComposer
	{
		return $this->_webpage_composer;
	}

	public function requireWebpageComposer(): iWebpageComposer
	{
		if ($this->_webpage_composer === null) {
			Kernel::abort('Form HTML rendering requires full webpage composition context. (' . $this->_form_type . ')');
		}

		return $this->_webpage_composer;
	}

	public function getFormType(): string
	{
		return $this->_form_type;
	}

	public function getFormId(): string
	{
		return $this->_form_id;
	}

	public function getFormInstanceId(): string
	{
		return $this->_form_instance_id;
	}

	public function getReferer(): string
	{
		return $this->referer;
	}

	public function getMode(): string
	{
		return $this->_mode;
	}

	public function focusable(): bool
	{
		return $this->_meta->focusable;
	}

	/**
	 * @return FormMetadata
	 */
	public function getMeta(): FormMetadata
	{
		return $this->_meta;
	}

	public function getItemId(): ?int
	{
		return $this->_item_id;
	}

	/**
	 * @return FormInput[]
	 */
	public function getFormInputs(): array
	{
		return $this->_form_inputs;
	}

	public function getInput(string $fieldname): ?FormInput
	{
		return $this->_form_inputs[$fieldname] ?? null;
	}

	public function getInputByKey(string $key): ?FormInput
	{
		foreach ($this->_form_inputs as $input) {
			if ($input->getKey() === $key) {
				return $input;
			}
		}

		return null;
	}

	public function isModified(string $fieldname): bool
	{
		return
			($this->getMode() === AbstractForm::_MODE_CREATE)
			|| ($this->getInput($fieldname)->getValue() != $this->initvalues[$fieldname]);
	}

	public function isValid(): bool
	{
		foreach ($this->_form_inputs as $input) {
			if (!$input->isValid()) {
				return false;
			}
		}

		return true;
	}

	protected function _processSavedata(): void
	{
		foreach ($this->_form_inputs as $input) {
			if ($input->save) {
				$this->savedata[$input->fieldname] = $input->getValue();
			}
		}
	}

	/**
	 * @param array<string, mixed>|null $payload
	 * @param array<string, mixed> $files
	 */
	public function process(?array $payload = null, array $files = []): FormResult
	{
		$payload ??= Request::getPOST();
		$submit_button = (string)($payload['submit_button'] ?? '');

		if (!$this->hasRole()) {
			return FormResult::denied(new ApiError('FORM_DENIED', t('response_error.access_denied')));
		}

		$this->bind($payload, $files);

		if ($submit_button === self::_SUBMIT_VALUE_CANCEL) {
			return FormResult::cancel();
		}

		if ($submit_button !== self::_SUBMIT_VALUE_SAVE) {
			return FormResult::invalid([
				'submit_button' => [t('common.error_save')],
			]);
		}

		$this->_validateData();

		if (!$this->isValid()) {
			return FormResult::invalid($this->getErrorsByField());
		}

		$this->_processSavedata();
		$this->commit();

		return FormResult::success($this->savedata);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param array<string, mixed> $files
	 */
	public function bind(array $payload, array $files = []): void
	{
		foreach ($this->_form_inputs as $input) {
			$input->bindSubmittedValue($payload, $files);
		}
	}

	/**
	 * @return array<string, list<string>>
	 */
	public function getErrorsByField(): array
	{
		$errors = [];

		foreach ($this->_form_inputs as $input) {
			$field_errors = $input->getErrors();

			if ($field_errors !== []) {
				$errors[$input->getKey()] = $field_errors;
			}
		}

		return $errors;
	}

	private function applySubmittedRenderState(): void
	{
		$state = FormSubmissionStateStore::get($this);

		if ($state === null) {
			return;
		}

		$this->bind($state['payload'], $state['files']);

		foreach ($state['result']->errors() as $key => $field_errors) {
			$input = $this->getInputByKey((string)$key);

			if ($input === null) {
				continue;
			}

			foreach ($field_errors as $field_error) {
				$input->addError($field_error);
			}
		}
	}

	protected function _validateData(): void
	{
		foreach ($this->_form_inputs as $input) {
			$input->doValidations();
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public function buildTree(): array
	{
		$hidden_fields = [];
		$rows = [];
		$current_row_items = [];
		$current_row_id = null;

		foreach ($this->_form_inputs as $input) {
			$input_tree = $input->buildTree();

			if ($input instanceof FormInputHidden) {
				$hidden_fields[] = $input_tree;

				continue;
			}

			if ($current_row_items === [] || $input->first_in_row) {
				if ($current_row_items !== []) {
					$rows[] = $this->buildRowTree((string)$current_row_id, $current_row_items);
				}

				$current_row_items = [];
				$current_row_id = $this->getRowId($input->fieldname);
			}

			$current_row_items[] = $input_tree;

			if ($input->last_in_row) {
				$rows[] = $this->buildRowTree((string)$current_row_id, $current_row_items);
				$current_row_items = [];
				$current_row_id = null;
			}
		}

		if ($current_row_items !== []) {
			$rows[] = $this->buildRowTree((string)$current_row_id, $current_row_items);
		}

		$submit_context = FormSubmitContext::fromForm($this, $this->_render_context);
		$contents = [
			'hidden_fields' => $hidden_fields,
			'rows' => $rows,
		];

		return [
			'type' => 'widget',
			'component' => 'form',
			'props' => [
				'form_id' => $this->getFormId(),
				'form_instance_id' => $this->getFormInstanceId(),
				'form_descriptor_id' => $this->getFormType(),
				'form_name' => $this->getFormType(),
				'mode' => $this->getMode(),
				'action' => Url::getUrl('form.submit'),
				'method' => 'post',
				'form_class' => $this->getMeta()->template,
				'title' => $this->getMeta()->title,
				'sub_title' => $this->getMeta()->sub_title,
				'autocomplete' => $this->getMeta()->autocomplete,
				'focusable' => $this->focusable(),
				'button_save' => $this->normalizeButton($this->getMeta()->formButtonSave),
				'button_cancel' => $this->normalizeButton($this->getMeta()->formButtonCancel),
				'post_javascript_file' => $this->getMeta()->postJavascriptFile,
				'field_refs' => $this->buildFieldRefs(),
				'submit_context' => $submit_context->toHiddenFields(),
			],
			'contents' => $contents,
			'slots' => $contents,
			'meta' => [
				'html' => [
					'wrapper_template' => $this->getMeta()->template,
				],
			],
		];
	}

	/**
	 * @param list<array<string, mixed>> $items
	 * @return array<string, mixed>
	 */
	private function buildRowTree(string $row_id, array $items): array
	{
		$contents = [
			'content' => $items,
		];

		return [
			'type' => 'sub',
			'component' => 'form.row',
			'props' => [
				'row_id' => $row_id,
			],
			'contents' => $contents,
			'slots' => $contents,
		];
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	private function buildFieldRefs(): array
	{
		$field_refs = [];

		foreach ($this->_form_inputs as $fieldname => $input) {
			$field_refs[$fieldname] = [
				'id' => (string)$input->id,
				'key' => $input->getKey(),
				'name' => $input->getPayloadName(),
				'row_id' => 'row_' . (string)$input->id,
			];
		}

		return $field_refs;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function normalizeButton(?FormButton $button): ?array
	{
		if ($button === null) {
			return null;
		}

		return [
			'text' => (string)$button->text,
			'icon' => $button->icon,
			'class' => $button->class,
		];
	}

	public function getInputId(string $field): ?string
	{
		if (!isset($this->_form_inputs[$field])) {
			Kernel::abort(__FILE__ . ', line ' . __LINE__ . ":Field <i>{$field}</i> doesnt exists in form: <i>{$this->getFormType()}</i>");
		}

		return $this->_form_inputs[$field]->id;
	}

	public function getInputKey(string $field): string
	{
		if (!isset($this->_form_inputs[$field])) {
			Kernel::abort(__FILE__ . ', line ' . __LINE__ . ":Field <i>{$field}</i> doesnt exists in form: <i>{$this->getFormType()}</i>");
		}

		return $this->_form_inputs[$field]->getKey();
	}

	public function getRowId(string $field): string
	{
		if (!isset($this->_form_inputs[$field])) {
			echo ">'" . '>"' . '--></script>';
			var_dump($this->_form_inputs);
			var_dump($field);
			Kernel::abort(__FILE__ . ', line ' . __LINE__ . ":Field <i>{$field}</i> doesnt exists in form: <i>{$this->getFormType()}</i>");
		}

		return 'row_' . $this->_form_inputs[$field]->id;
	}

	public function renderLabelStyle(): void
	{
		if (!empty($this->_meta->labelWidth)) {
			echo ' style="width:' . $this->_meta->labelWidth . '"';
		}
	}

	public function getRedirectTargetForResult(FormResult $result, FormSubmitContext $context): string
	{
		return $context->returnTarget !== '' ? $context->returnTarget : Url::getCurrentHost();
	}

	public function getSavedataHtml(): string
	{
		$out = '<table style="border-collapse:collapse;font-family:sans-serif;margin:20px;width:700px;" cellpadding="0" cellspacing="0">';

		foreach ($this->_form_inputs as $input) {
			if ($input->save === false) {
				continue;
			}

			$out .= '<tr>';

			if (is_array($input->getValue())) {
				$out .= '<td colspan="2" align="center" style="width:25%;">' . $input->label . '</td></tr><td colspan="2">';
				$out .= self::convertArray2Html($input->getValue());
				$out .= '</td>';
			} else {
				$value = str_replace("\n", "<br>", $input->getValue());
				$out .= "<td style=\"border-bottom:1px solid #e8e8e8;padding:8px;font-family:serif;width:25%;\">{$input->label}</td><td style=\"border-bottom:1px solid #e8e8e8;padding:8px;\">{$value}</td>";
			}
			$out .= '</tr>';
		}

		$out .= '</table>';

		return $out;
	}

	public static function convertArray2Html(array $array): string
	{
		$out = '<table border="1" cellpadding="3" cellspacing="0">';

		foreach ($array as $key => $value) {
			$out .= '<tr>';

			if (is_array($value)) {
				$out .= '<td colspan="2" align="center">' . $key . '</td></tr><td colspan="2">';
				$out .= self::convertArray2Html($value);
				$out .= '</td>';
			} else {
				$value = str_replace("\n", "<br>", $value);
				$out .= "<td>{$key}</td><td>{$value}</td>";
			}
			$out .= '</tr>';
		}

		$out .= '</table>';

		return $out;
	}
}
