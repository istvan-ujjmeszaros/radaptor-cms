<?php

class WidgetRichText extends AbstractWidget
{
	public const string ID = 'rich_text';

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
		return Roles::hasRole(RoleList::ROLE_RICHTEXT_ADMINISTRATOR);
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/components/richtext/',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	public static function isWrapperStylingEnabled(): bool
	{
		return false;
	}

	public static function getContentLocaleStrategy(): ?WidgetContentLocaleStrategy
	{
		return new RichTextWidgetContentLocaleStrategy();
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		if ($connection->getExtraparam('content_id')) {
			$content_id = $connection->getExtraparam('content_id');
		} else {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => t('cms.richtext.not_set'),
			]);
		}

		if (!RichTextLocaleService::contentMatchesConnectionLocale((int) $content_id, $connection->connection_id)) {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => t('cms.richtext.locale_mismatch'),
			]);
		}

		$contents = EntityRichtext::findById($content_id)?->dto();

		if (!is_array($contents)) {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => t('cms.richtext.not_set'),
			]);
		}

		$settings = WidgetSettings::getSettings($connection->connection_id);

		return $this->createComponentTree('RichText', [
			'style' => $connection->getStyle(),
			'class' => Themes::getClass($tree_build_context, $connection->connection_id),
			'title' => $contents['title'],
			'settings' => $settings,
			'content' => $contents['content'],
			'extraparams' => $connection->getExtraparams(),
		]);
	}

	public function getEditableCommands(WidgetConnection $connection): array
	{
		$edit = new WidgetEditCommand();

		if ($connection->getExtraparam('content_id')) {
			// Content is already assigned, edit it
			$edit->title = t('cms.richtext.widget.edit');
			$edit->icon = IconNames::EDIT;
			$edit->url = Form::getSeoUrl(FormList::RICHTEXT, $connection->getExtraparam('content_id'), null, ['connection_id' => $connection->connection_id]);
		} else {
			// No content assigned, create new one
			$edit->title = t('cms.richtext.widget.create');
			$edit->icon = IconNames::CONTENT_ADD;
			$edit->url = Form::getSeoUrl(FormList::RICHTEXT, null, null, ['connection_id' => $connection->connection_id]);
		}

		$choose = new WidgetEditCommand();
		$choose->title = t('cms.richtext.widget.choose');
		$choose->icon = IconNames::CHOOSE;
		$choose->url = Form::getSeoUrl(FormList::RICHTEXTCONTENTSELECT, $connection->getExtraparam('content_id'), null, ['connection_id' => $connection->connection_id]);

		$return = [];

		if (Roles::hasRole(RoleList::ROLE_RICHTEXT_ADMINISTRATOR)) {
			$return[] = $edit;
			$return[] = $choose;
		}

		return $return;
		//return array($edit, $choose);
	}
	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return true;
	}
}
