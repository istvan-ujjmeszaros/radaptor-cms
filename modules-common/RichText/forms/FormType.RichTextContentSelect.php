<?php

class FormTypeRichTextContentSelect extends AbstractForm
{
	public const string ID = 'rich_text_select';
	private const string LOCALE_FILTER_ALL = '_all';

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
			'path' => '/admin/components/richtext/selector/',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	public function hasRole(): bool
	{
		return Roles::hasRole(RoleList::ROLE_RICHTEXT_ADMINISTRATOR);
	}

	public function commit(): void
	{
		switch ($this->getMode()) {
			case self::_MODE_UPDATE:
			case self::_MODE_CREATE:

				$connection_id = Request::_GET('connection_id', Request::DEFAULT_ERROR);
				$content_id = (int) ($this->savedata['content_id'] ?? 0);

				if (!RichTextLocaleService::contentMatchesConnectionLocale($content_id, $connection_id)) {
					SystemMessages::addSystemMessage(t('cms.richtext.locale_mismatch'));

					break;
				}

				if (AttributeHandler::addAttribute(new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, $connection_id), ['content_id' => $content_id])) {
					SystemMessages::addSystemMessage(t('cms.richtext.assigned'));
				} else {
					SystemMessages::addSystemMessage(t('common.error_save'));
				}

				break;
		}
	}

	public function setMetadata(): void
	{
		if ($this->_mode == self::_MODE_CREATE) {
			$this->_meta->title = t('cms.richtext.select.form.title');
		} else {
			$this->_meta->title = t('cms.richtext.select.form.title');
			$this->_meta->sub_title = EntityRichtext::getContentTitle($this->getItemId());
		}
	}

	public function setInitValues(): void
	{
		$data = AttributeHandler::getAttributes(new AttributeResourceIdentifier(ResourceNames::WIDGET_CONNECTION, Request::_GET('connection_id')));

		$this->initvalues = $data;
	}

	public function makeInputs(): void
	{
		$current_id = (int) ($this->initvalues['content_id'] ?? 0);
		$current_locale = $current_id > 0 ? EntityRichtext::getContentLocale($current_id) : null;
		$selected_locale = $this->resolveLocaleFilter();
		$locale_options = $this->buildLocaleFilterOptions($current_locale);
		$show_locale_labels = $selected_locale === null
			|| count($locale_options) > 2
			|| ($current_locale !== null && !LocaleService::isEnabled($current_locale));

		if ($show_locale_labels) {
			$locale_filter = new FormInputLinkGroup('locale_filter', $this);
			$locale_filter->label = t('cms.richtext.field.locale.label');
			$locale_filter->explanation = t('cms.richtext.field.locale.explanation');
			$locale_filter->values = $this->buildLocaleFilterLinks($locale_options, $selected_locale);
		}

		$content_id = new FormInputSelect('content_id', $this);
		$content_id->label = t('cms.richtext.field.content.label');
		$content_id->values = EntityRichtext::getListForSelect(
			$selected_locale,
			$current_id,
			$show_locale_labels
		);
	}

	private function resolveLocaleFilter(): ?string
	{
		$requested = Request::_GET('locale', null);

		if (is_string($requested) && trim($requested) !== '') {
			$requested = trim($requested);

			if (in_array(strtolower($requested), [self::LOCALE_FILTER_ALL, 'all', '*'], true)) {
				return null;
			}

			$locale = LocaleService::tryCanonicalize($requested);

			if ($locale !== null) {
				return $locale;
			}
		}

		return RichTextLocaleService::getLocaleForCurrentRequest();
	}

	/**
	 * @param list<array{inputtype: string, value: string, label: string}> $locale_options
	 * @return list<array{url: string, label: string, active: bool}>
	 */
	private function buildLocaleFilterLinks(array $locale_options, ?string $selected_locale): array
	{
		$selected_key = $selected_locale ?? self::LOCALE_FILTER_ALL;
		$links = [];

		foreach ($locale_options as $option) {
			$value = (string) ($option['value'] ?? '');

			if ($value === '') {
				continue;
			}

			$links[] = [
				'url' => Url::modifyCurrentUrl(['locale' => $value]),
				'label' => (string) ($option['label'] ?? $value),
				'active' => $value === $selected_key,
			];
		}

		return $links;
	}

	/**
	 * @return list<array{inputtype: string, value: string, label: string}>
	 */
	private function buildLocaleFilterOptions(?string $current_locale): array
	{
		$enabled = array_fill_keys(LocaleService::enabledForNewContent(), true);
		$options = [[
			'inputtype' => 'option',
			'value' => self::LOCALE_FILTER_ALL,
			'label' => t('common.all'),
		]];

		foreach (LocaleService::allForExistingContentEditing($current_locale) as $locale) {
			$label = LocaleRegistry::getDisplayLabel($locale);
			$suffixes = [];

			if (!isset($enabled[$locale])) {
				$suffixes[] = t('locale_admin.status.disabled');
			}

			if ($current_locale === $locale && !isset($enabled[$locale])) {
				$suffixes[] = t('user.locale.current_label');
			}

			if ($suffixes !== []) {
				$label .= ' (' . implode(', ', $suffixes) . ')';
			}

			$options[] = [
				'inputtype' => 'option',
				'value' => $locale,
				'label' => $label,
			];
		}

		return $options;
	}
}
