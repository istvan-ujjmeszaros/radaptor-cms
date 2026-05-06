<?php

abstract class AbstractForm implements iForm, iListable
{
	public const string _MODE_CREATE = 'insert';
	public const string _MODE_UPDATE = 'update';
	public const string _SUBMIT_VALUE_CANCEL = 'cancel';
	public const string _SUBMIT_VALUE_SAVE = 'save';

	public array $savedata = [];
	public array $initvalues;
	protected string $_form_id;
	protected string $_mode;
	private int $_inputCounter = 0;

	/** @var FormInput[] */
	protected array $_form_inputs;
	protected ?int $_item_id;

	protected FormMetadata $_meta;

	private string $referer;

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

	public function __construct(protected string $_form_type, string $form_id, protected iTreeBuildContext $_tree_build_context, ?string $referer = null)
	{
		$this->_form_id = 'f' . $form_id;
		$this->_meta = new FormMetadata();
		$this->_webpage_composer = $this->_tree_build_context instanceof iWebpageComposer ? $this->_tree_build_context : null;

		if (Request::_GET('item_id')) {
			$this->_mode = self::_MODE_UPDATE;
			$this->_item_id = Request::_GET('item_id');
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

			if ($this->_meta->enableAutoReferer && !self::isHtmxRequest() && (Kernel::getReferer() != '') && !Url::CurrentEqualsToReferer()) {
				Url::redirect(Url::modifyCurrentUrl(['referer' => Url::sanitizeRefererUrl((string) Kernel::getReferer())]));
			}
		}

		if ($this->_mode == self::_MODE_UPDATE && !isset($_POST[$this->_form_id])) {
			$this->setInitValues();
		}

		$this->makeInputs();

		if (!current($this->_form_inputs) instanceof FormInput) {
			Kernel::abort('makeInputs() must return array of FormInput elements! (' . $this->_form_type . ')');
		}

		$this->_processForm();
	}

	private static function isHtmxRequest(): bool
	{
		$server = RequestContextHolder::current()->SERVER ?: $_SERVER;

		return strtolower(trim((string)($server['HTTP_HX_REQUEST'] ?? $server['http_hx_request'] ?? ''))) === 'true';
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

	private function _cancelForm(): never
	{
		$this->_destroyForm();

		exit;
	}

	private function _saveForm(): void
	{
		$this->_validateData();

		if ($this->isValid()) {
			$this->_processSavedata();

			$this->commit();

			$this->_destroyForm();
		}

		$this->_setErrorMessages();
	}

	private function _processForm(): void
	{
		//var_dump($_POST);
		//exit;
		if (!isset($_POST['submit_button'])) {
			return;
		}

		if ($_POST['submit_button'] == self::_SUBMIT_VALUE_CANCEL) {
			$this->_cancelForm();
		} elseif ($_POST['submit_button'] == self::_SUBMIT_VALUE_SAVE) {
			$this->_saveForm();
		} // IE workaround
		elseif (mb_strpos((string) $_POST['submit_button'], '<!--' . self::_SUBMIT_VALUE_CANCEL . '-->') !== false) {
			$this->_cancelForm();
		} elseif (mb_strpos((string) $_POST['submit_button'], '<!--' . self::_SUBMIT_VALUE_SAVE . '-->') !== false) {
			$this->_saveForm();
		}
	}

	private function _setErrorMessages(): void
	{
		if (!$this->_meta->useErrorWindow) {
			return;
		}

		foreach ($this->_form_inputs as $input) {
			foreach ($input->getErrors() as $error) {
				SystemMessages::_error($error, $this->_meta->errorHeader);
			}
		}
	}

	private function _destroyForm(): void
	{
		Url::redirect($this->referer);
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

		return [
			'type' => 'widget',
			'component' => 'form',
			'props' => [
				'form_id' => $this->getFormId(),
				'form_name' => $this->getFormType(),
				'mode' => $this->getMode(),
				'action' => '',
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
			],
			'contents' => [
				'hidden_fields' => $hidden_fields,
				'rows' => $rows,
			],
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
		return [
			'type' => 'sub',
			'component' => 'form.row',
			'props' => [
				'row_id' => $row_id,
			],
			'contents' => [
				'content' => $items,
			],
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
				'name' => (string)$input->id,
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
