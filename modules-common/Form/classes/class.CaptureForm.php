<?php

declare(strict_types=1);

final class CaptureForm extends AbstractForm
{
	private FormDefinitionResolution $_resolution;

	/** @var array<string, mixed> */
	private array $_capture_render_context = [];

	/**
	 * @param array<string, mixed> $render_context
	 */
	public function __construct(string $_form_type, string $form_id, iTreeBuildContext $_tree_build_context, ?string $referer = null, array $render_context = [])
	{
		$resolution = $render_context['form_definition_resolution'] ?? null;

		if (!$resolution instanceof FormDefinitionResolution || !$resolution->isCapture()) {
			$resolution = FormDefinitionResolver::requireResolution($_form_type);
		}

		$this->_resolution = $resolution;
		$this->_capture_render_context = $render_context;
		parent::__construct($_form_type, $form_id, $_tree_build_context, $referer, $render_context);
	}

	public static function getName(): string
	{
		return t('form.capture.name');
	}

	public static function getDescription(): string
	{
		return t('form.capture.description');
	}

	public static function getListVisibility(): bool
	{
		return false;
	}

	public static function getDefaultPathForCreation(): array
	{
		return [];
	}

	public function hasRole(): bool
	{
		return true;
	}

	public function setMetadata(): void
	{
		$descriptor = $this->_resolution->descriptor();
		$this->_meta->title = $this->resolveDescriptorText($descriptor['title'] ?? '');
		$this->_meta->sub_title = $this->resolveDescriptorText($descriptor['description'] ?? $descriptor['sub_title'] ?? '');
		$this->_meta->enableAutoReferer = false;
		$this->_meta->formButtonCancel = null;
		$this->_meta->formButtonSave = new FormButton(
			$this->resolveDescriptorText($descriptor['submit_label'] ?? ['key' => 'form.capture.submit']),
			IconNames::FORM_SAVE,
			FormButton::CLASS_POSITIVE,
		);
	}

	public function makeInputs(): void
	{
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function getDescriptor(): ?array
	{
		return $this->_resolution->descriptor();
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param array<string, mixed> $files
	 */
	public function bind(array $payload, array $files = []): void
	{
		parent::bind($payload, $files);

		foreach ($this->normalizersByFieldName() as $fieldname => $normalizers) {
			$input = $this->getInput($fieldname);

			if ($input === null) {
				continue;
			}

			$input->setValue($this->applyNormalizers($input->getValue(), $normalizers));
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

		if ($this->isHoneypotFilled($payload)) {
			return FormResult::denied(new ApiError('FORM_CAPTURE_DENIED', t('response_error.access_denied')));
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

		$submission_service = new FormCaptureSubmissionService();

		if ($submission_service->isRateLimited($this->_resolution, $this->_resolution->security())) {
			return FormResult::denied(new ApiError('FORM_CAPTURE_RATE_LIMITED', t('form.capture.error_rate_limited')));
		}

		$this->_processSavedata();
		$this->commit();

		return FormResult::success($this->savedata);
	}

	protected function _processSavedata(): void
	{
		foreach ($this->_form_inputs as $input) {
			if ($input->save) {
				$this->savedata[$input->getKey()] = $input->getValue();
			}
		}
	}

	public function commit(): void
	{
		$submission_id = (new FormCaptureSubmissionService())->store(
			$this->_resolution,
			$this->savedata,
			$this->_capture_render_context,
		);
		$this->savedata['_submission_id'] = $submission_id;

		try {
			(new FormHookInvocationService())->invokeForSubmission(
				$this->_resolution,
				$submission_id,
				$this->savedata,
				$this->_capture_render_context,
			);
		} catch (Throwable $exception) {
			Kernel::logException($exception, 'Capture form hook invocation failed after submission commit', [
				'definition_slug' => $this->_resolution->definitionSlug(),
				'submission_id' => $submission_id,
			]);
		}
	}

	public function buildTree(): array
	{
		$tree = parent::buildTree();
		$honeypot = $this->buildHoneypotTree();

		if (!isset($tree['contents']['hidden_fields']) || !is_array($tree['contents']['hidden_fields'])) {
			$tree['contents']['hidden_fields'] = [];
		}

		$tree['contents']['hidden_fields'][] = $honeypot;

		return SduiNode::normalize($tree);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function isHoneypotFilled(array $payload): bool
	{
		$field_name = (string)($this->_resolution->security()['honeypot']['field_name'] ?? FormCaptureDescriptorSchemaValidator::DEFAULT_HONEYPOT_FIELD);
		$value = $payload[$field_name] ?? null;

		if (is_array($value)) {
			return $value !== [];
		}

		return trim((string)$value) !== '';
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildHoneypotTree(): array
	{
		$field_name = (string)($this->_resolution->security()['honeypot']['field_name'] ?? FormCaptureDescriptorSchemaValidator::DEFAULT_HONEYPOT_FIELD);

		return SduiNode::create(
			'form.honeypot',
			[
				'id' => $this->getFormId() . '_honeypot',
				'name' => $field_name,
				'label' => t('form.capture.honeypot.label'),
			],
		);
	}

	/**
	 * @return array<string, list<string>>
	 */
	private function normalizersByFieldName(): array
	{
		$normalizers = [];

		foreach ($this->_resolution->descriptor()['fields'] ?? [] as $field) {
			if (!is_array($field)) {
				continue;
			}

			$fieldname = trim((string)($field['name'] ?? ''));
			$field_normalizers = $field['normalizers'] ?? [];

			if ($fieldname === '' || !is_array($field_normalizers)) {
				continue;
			}

			foreach ($field_normalizers as $normalizer) {
				if (is_scalar($normalizer)) {
					$normalizers[$fieldname][] = FormCaptureDescriptorSchemaValidator::normalizeNormalizerName((string)$normalizer);
				}
			}
		}

		return $normalizers;
	}

	/**
	 * @param list<string> $normalizers
	 */
	private function applyNormalizers(mixed $value, array $normalizers): mixed
	{
		if (is_array($value)) {
			foreach ($value as $key => $item) {
				$value[$key] = $this->applyNormalizers($item, $normalizers);
			}

			return $value;
		}

		if ($value === null || !is_scalar($value)) {
			return $value;
		}

		$value = (string)$value;

		foreach ($normalizers as $normalizer) {
			$value = match ($normalizer) {
				'trim' => trim($value),
				'lowercase' => mb_strtolower($value),
				'collapse_whitespace' => preg_replace('/\s+/u', ' ', $value) ?? $value,
				default => $value,
			};
		}

		return $value;
	}

	private function resolveDescriptorText(mixed $value): string
	{
		return FormDescriptorAdapter::resolveDescriptorText($value);
	}
}
