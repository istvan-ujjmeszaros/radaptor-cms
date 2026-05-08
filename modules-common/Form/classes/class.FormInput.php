<?php

abstract class FormInput implements iFormInput, Stringable
{
	protected mixed $_value = null;
	public ?string $id = null;
	public bool $save = true;
	public ?string $label = null;
	public ?string $explanation = null;
	public mixed $initvalue = null;
	public bool $readonly = false;
	public string $width = '';
	public string $height = '';
	public bool $first_in_row = true;
	public bool $last_in_row = true;
	public ?string $labelstyle = null; // This is used via a template prop

	/** @var iFormValidator[] $validators */
	private array $_validators = [];
	protected array $_errors = [];

	protected ?AbstractForm $_parent = null;

	public function __toString(): string
	{
		return (string) $this->_value;
	}

	public function isValid(): bool
	{
		return count($this->_errors) == 0;
	}

	public function addError(string $message): void
	{
		$this->_errors[] = $message;
	}

	public function getErrors(): array
	{
		return $this->_errors;
	}

	public function getParent(): ?AbstractForm
	{
		return $this->_parent;
	}

	public function __set(string $name, mixed $value): void
	{
		Kernel::abort('Set unknown FormInput property is prohibited: ' . $name);
	}

	public function __get(string $name): void
	{
		Kernel::abort('Get unknown FormInput property is prohibited: ' . $name);
	}

	/**
	 * @param string $fieldname
	 * @param AbstractForm $parent
	 */
	public function __construct(
		public string $fieldname,
		AbstractForm $parent
	) {
		$id = $parent->getNextInputId();
		$this->id = $parent->getFormId() . '_input_' . $id;

		$this->_parent = $parent;

		if (isset($_POST[$this->id])) {
			$this->_value = $_POST[$this->id];
		} elseif (count($_POST) > 0) {   // ez remélhetőleg csak checkbox-nál fordul elő
			$this->_value = null;
		} elseif (isset($this->_parent->initvalues[$this->fieldname])) {
			$this->_value = $this->_parent->initvalues[$this->fieldname];
		}

		if (Request::_GET('formdata-' . $this->fieldname, false) !== false) {
			$this->_value = Request::_GET('formdata-' . $this->fieldname, false);
			$this->readonly = true;
		}

		$parent->addInput($this);
	}

	public function getValue(): mixed
	{
		if (!is_null($this->_value)) {
			return $this->_value;
		} else {
			return $this->initvalue;
		}
	}

	public function setValue(mixed $value): void
	{
		$this->_value = $value;
	}

	public function fetchStyle(): string
	{
		$style = $this->width == '' ? '' : 'width: ' . $this->width . ";";
		$style .= $this->height == '' ? '' : 'height: ' . $this->height . ";";

		if ($style != '') {
			return ' style="' . $style . '"';
		}

		return '';
	}

	public function fetchValue(): string
	{
		return htmlspecialchars($this->getValue() ?? '', ENT_QUOTES | ENT_SUBSTITUTE);
	}

	public function getInputErrorString(): string
	{
		$return = '';

		$i = 0;

		foreach ($this->_errors as $error) {
			++$i;

			$return .= $error;

			if ($i < count($this->_errors)) {
				$return .= "<br>\n";
			}
		}

		return $return;
	}

	public function addValidator(iFormValidator $validator): iFormValidator
	{
		$validator->setParent($this);

		$this->_validators[] = $validator;

		return $validator;
	}

	public function doValidations(): void
	{
		foreach ($this->_validators as $validator) {
			if (!$validator instanceof iFormValidator) {
				continue;
			}

			$validator->validate();
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public function buildTree(): array
	{
		$contents = [];

		if ($this->supportsHelperSlot()) {
			$contents['helper'] = [$this->buildHelperTree()];
		}

		$meta = [];

		if ($this instanceof FormInputTextarea && $this->editor !== '') {
			$meta['html']['editor'] = $this->editor;
		}

		return [
			'type' => 'sub',
			'component' => 'form.input.' . $this->getInputtype(),
			'props' => $this->buildComponentProps(),
			'contents' => $contents,
			'meta' => $meta,
		];
	}

	protected function supportsHelperSlot(): bool
	{
		return !in_array($this->getInputtype(), [
			FormInputClearFloat::INPUTTYPE,
			FormInputGroupEnd::INPUTTYPE,
			FormInputHidden::INPUTTYPE,
			FormInputWidgetGroupBeginning::INPUTTYPE,
		], true);
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function buildComponentProps(): array
	{
		$input_style = $this->buildStyleString();
		$label_style = $this->buildLabelStyleString();

		$props = [
			'input_type' => $this->getInputtype(),
			'fieldname' => $this->fieldname,
			'id' => (string)$this->id,
			'name' => (string)$this->id,
			'row_id' => 'row_' . (string)$this->id,
			'label' => $this->label ?? '',
			'value' => $this->getValue(),
			'readonly' => $this->readonly,
			'save' => $this->save,
			'first_in_row' => $this->first_in_row,
			'last_in_row' => $this->last_in_row,
			'errors' => $this->getErrors(),
			'error_string' => $this->getInputErrorString(),
			'info_string' => $this->explanation ?? '',
			'helper_target' => (string)$this->id,
			'input_style' => $input_style,
			'input_style_attr' => $input_style === '' ? '' : ' style="' . $input_style . '"',
			'label_style' => $label_style,
			'label_style_attr' => $label_style === '' ? '' : ' style="' . $label_style . '"',
			'validators' => $this->getValidatorDefinitions(),
		];

		return array_replace($props, $this->buildTypeSpecificProps());
	}

	protected function buildStyleString(): string
	{
		$style = $this->width == '' ? '' : 'width: ' . $this->width . ';';
		$style .= $this->height == '' ? '' : 'height: ' . $this->height . ';';

		return $style;
	}

	protected function buildLabelStyleString(): string
	{
		$style = $this->labelstyle ?? '';

		if (!empty($this->_parent?->getMeta()->labelWidth)) {
			$style .= ($style !== '' ? ' ' : '') . 'width:' . $this->_parent->getMeta()->labelWidth;
		}

		return trim($style);
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function buildTypeSpecificProps(): array
	{
		return match ($this->getInputtype()) {
			FormInputText::INPUTTYPE => [
				'autocomplete_url' => $this instanceof FormInputText ? ($this->autocomplete_url ?? '') : '',
				'connected_autocomplete_fieldname' => $this instanceof FormInputText ? ($this->connected_autocomplete_fieldname ?? '') : '',
				'connected_autocomplete_input_id' => ($this instanceof FormInputText && !empty($this->connected_autocomplete_fieldname))
					? ($this->_parent?->getInputId($this->connected_autocomplete_fieldname) ?? '')
					: '',
				'connected_autocomplete_row_id' => ($this instanceof FormInputText && !empty($this->connected_autocomplete_fieldname))
					? ($this->_parent?->getRowId($this->connected_autocomplete_fieldname) ?? '')
					: '',
			],
			FormInputSelect::INPUTTYPE => [
				'values' => $this instanceof FormInputSelect ? $this->values : [],
				'required' => $this instanceof FormInputSelect ? $this->required : true,
				'placeholder_label' => t('common.choose_placeholder'),
			],
			FormInputLinkGroup::INPUTTYPE => [
				'values' => $this instanceof FormInputLinkGroup ? $this->values : [],
			],
			FormInputCheckbox::INPUTTYPE => [
				'checked' => (bool)$this->getValue(),
			],
			FormInputCheckboxgroup::INPUTTYPE => [
				'values' => $this instanceof FormInputCheckboxgroup ? $this->values : [],
				'helper_target' => (string)$this->id . '_1',
			],
			FormInputRadiogroup::INPUTTYPE => [
				'values' => $this instanceof FormInputRadiogroup ? $this->values : [],
				'helper_target' => (string)$this->id . '_1',
			],
			FormInputTextarea::INPUTTYPE => [
				'editor' => $this instanceof FormInputTextarea ? $this->editor : '',
				'toolbar' => $this instanceof FormInputTextarea ? $this->toolbar : '',
			],
			FormInputDate::INPUTTYPE, FormInputDateTime::INPUTTYPE => [
				'calendar_title' => t('common.calendar'),
			],
			FormInputWidgetGroupBeginning::INPUTTYPE => [
				'class' => $this instanceof FormInputWidgetGroupBeginning ? $this->class : '',
			],
			default => [],
		};
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	protected function getValidatorDefinitions(): array
	{
		$validators = [];

		foreach ($this->_validators as $validator) {
			if ($validator instanceof FormValidator) {
				$validators[] = $validator->toTreeData();
			}
		}

		return $validators;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildHelperTree(): array
	{
		return [
			'type' => 'sub',
			'component' => 'form.helper',
			'props' => [
				'target' => $this->buildComponentProps()['helper_target'] ?? $this->id,
				'error_string' => $this->getInputErrorString(),
				'info_string' => $this->explanation ?? '',
			],
			'contents' => [],
		];
	}
}
