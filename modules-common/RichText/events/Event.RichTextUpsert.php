<?php

declare(strict_types=1);

class EventRichTextUpsert extends AbstractEvent implements iBrowserEventDocumentable
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_RICHTEXT_ADMINISTRATOR)
			? PolicyDecision::allow()
			: PolicyDecision::deny('richtext administrator role required');
	}

	public static function describeBrowserEvent(): array
	{
		return [
			'event_name' => 'richtext.upsert',
			'group' => 'CMS Authoring',
			'name' => 'Upsert rich text content',
			'summary' => 'Creates or updates a RichText content record by stable name.',
			'description' => 'Creates or updates a RichText content record and returns the content id that can be assigned to WidgetRichText.',
			'request' => [
				'method' => 'POST',
				'params' => [
					BrowserEventDocumentationHelper::param('name', 'body', 'string', true, 'Stable RichText name.'),
					BrowserEventDocumentationHelper::param('locale', 'body', 'string', false, 'BCP 47 content locale. Defaults to the current request locale.'),
					BrowserEventDocumentationHelper::param('title', 'body', 'string', false, 'Human-readable RichText title. Leave empty when the rendered widget should not show a heading.'),
					BrowserEventDocumentationHelper::param('content', 'body', 'string', true, 'HTML content.'),
					BrowserEventDocumentationHelper::param('content_type', 'body', 'string', false, 'Content type, defaults to article.'),
				],
			],
			'response' => [
				'kind' => 'json',
				'content_type' => 'application/json',
				'description' => 'Returns content id and whether a new record was created.',
			],
			'authorization' => [
				'visibility' => 'role',
				'description' => 'Requires richtext administrator role.',
			],
			'mcp' => [
				'enabled' => true,
				'tool_name' => 'radaptor.richtext.upsert',
				'risk' => 'write',
			],
			'notes' => [],
			'side_effects' => BrowserEventDocumentationHelper::lines('Creates or updates a richtext row.'),
		];
	}

	public function run(): void
	{
		$name = trim((string) Request::_POST('name', ''));
		$raw_locale = Request::_POST('locale', null);
		$locale = Kernel::getLocale();

		if ($raw_locale !== null && trim((string) $raw_locale) !== '') {
			$posted_locale = LocaleService::tryCanonicalize((string) $raw_locale);

			if ($posted_locale === null) {
				ApiResponse::renderError('INVALID_LOCALE', t('locale_admin.message.invalid_locale'), 400);

				return;
			}

			$locale = $posted_locale;
		}

		$title = trim((string) Request::_POST('title', ''));
		$content = (string) Request::_POST('content', '');
		$content_type = trim((string) Request::_POST('content_type', 'article'));

		if ($name === '') {
			ApiResponse::renderError('MISSING_NAME', t('common.missing_required_url_params'), 400);

			return;
		}

		if (trim($content) === '') {
			ApiResponse::renderError('MISSING_CONTENT', t('common.missing_required_url_params'), 400);

			return;
		}

		if ($content_type === '') {
			$content_type = 'article';
		}

		try {
			$has_locale_column = RichTextLocaleService::hasRichTextLocaleColumn();
			$content_id = EntityRichtext::getContentIdByName($name, $locale);

			if ($content_id === EntityRichtext::ERROR_MULTIPLE) {
				ApiResponse::renderError('RICHTEXT_NAME_NOT_UNIQUE', t('cms.richtext.field.name.unique_error'), 400);

				return;
			}

			if ($has_locale_column && $content_id === EntityRichtext::ERROR_NOT_FOUND && !LocaleService::isEnabled($locale)) {
				ApiResponse::renderError('INVALID_LOCALE', t('locale_admin.message.invalid_locale'), 400);

				return;
			}

			$data = [
				'name' => $name,
				'title' => $title,
				'content' => $content,
				'content_type' => $content_type,
			];

			if ($has_locale_column) {
				$data['locale'] = $locale;
			}
			$created = false;

			if ($content_id === EntityRichtext::ERROR_NOT_FOUND) {
				$entity = EntityRichtext::createFromArray($data);
				$content_id = (int) $entity->pkey();
				$created = true;
			} else {
				EntityRichtext::updateById($content_id, $data);
			}

			ApiResponse::renderSuccess([
				'content_id' => $content_id,
				'name' => $name,
				'locale' => $locale,
				'title' => $title,
				'content_type' => $content_type,
				'created' => $created,
			]);
		} catch (Throwable $exception) {
			Kernel::logException($exception, 'RichText upsert failed');
			ApiResponse::renderError('RICHTEXT_UPSERT_FAILED', t('common.error_save'), 400);
		}
	}
}
