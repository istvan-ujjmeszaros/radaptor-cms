<?php

class Form
{
	public static function factory(string $form_type, string $form_id, iTreeBuildContext $tree_build_context): AbstractForm
	{
		$class = 'FormType' . ucwords($form_type);

		$form = new $class($form_type, $form_id, $tree_build_context);

		if ($form instanceof AbstractForm) {
			return $form;
		}

		Kernel::abort($class . ' must implement iForm!');
	}

	public static function getVisibleFormTypes(): array
	{
		$return = [];

		$formTypes = AutoloaderFromGeneratedMap::getFilteredList('FormType');

		foreach ($formTypes as $formType) {
			$formClassName = "FormType" . $formType;

			if (!class_exists($formClassName) || !is_subclass_of($formClassName, 'AbstractForm')) {
				SystemMessages::_error("Requested form class '{$formClassName}' does not exist or does not implement AbstractForm.");

				return [];
			}

			if (!$formClassName::getListVisibility()) {
				continue;
			}

			$return[] = [
				'inputtype' => 'option',
				'value' => $formType,
				'label' => $formClassName::getName(),
			];
		}

		return $return;
	}

	public static function getSeoUrl($form_id, $item_id = null, $referer = null, $extra_params = []): string
	{
		$referer ??= Request::_GET('referer', Url::getCurrentUrlForReferer());
		$referer = Url::sanitizeRefererUrl((string) $referer);

		$page_id = ResourceTypeWebpage::getWebpageIdByFormType($form_id);

		if (!$page_id) {
			$page_id = ResourceTypeWebpage::getWebpageIdByFormType('');

			if (!$page_id) {
				SystemMessages::_warning(t('cms.form.no_form_page_warning'));

				return '';
			}

			$extra_params['form_id'] = $form_id;
		}

		$url = Url::getSeoUrl($page_id);

		$extra_params_text = '';

		foreach ($extra_params as $key => $value) {
			$extra_params_text .= '&' . $key . '=' . urlencode((string) $value);
		}

		if (is_null($item_id)) {
			return $url . '?' . ltrim($extra_params_text, '&') . ($extra_params_text ? '&' : '') . 'referer=' . urlencode((string) $referer);
		} else {
			return $url . '?item_id=' . $item_id . $extra_params_text . '&referer=' . urlencode((string) $referer);
		}
	}
}
