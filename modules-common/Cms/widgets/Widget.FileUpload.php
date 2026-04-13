<?php

class WidgetFileUpload extends AbstractWidget
{
	public const string ID = 'file_upload';

	/**
	 * @return array<string, string>
	 */
	public static function buildStrings(): array
	{
		return [
			'widget.file_upload.name' => t('widget.file_upload.name'),
			'cms.file_upload.destination_path' => t('cms.file_upload.destination_path'),
			'cms.file.uploaded' => t('cms.file.uploaded'),
			'cms.file.upload_error' => t('cms.file.upload_error'),
			'common.upload' => t('common.upload'),
		];
	}

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
		return Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER);
	}

	public static function getDefaultPathForCreation(): array
	{
		return [
			'path' => '/admin/resources/upload/',
			'resource_name' => 'index.html',
			'layout' => 'admin_default',
		];
	}

	protected function buildAuthorizedTree(iTreeBuildContext $tree_build_context, WidgetConnection $connection, array $build_context = []): array
	{
		$ref_id = Request::_GET('ref_id');

		if (is_null($ref_id)) {
			return $this->buildStatusTree([
				'severity' => 'warning',
				'message' => t('cms.file_upload.unknown_destination'),
			]);
		}

		//$filtered_extraparams = Url::getExtraParamRealValues(Tickets::getEnabledUrlParams(), Url::getExtraParams($view));
		$extra_params = Url::getExtraParams($tree_build_context);

		return $this->createComponentTree('fileUpload', [
			'extraparams' => $extra_params,
			'ref_id' => $ref_id,
			'destination_attributes' => AttributeHandler::getAttributeArray(
				new AttributeResourceIdentifier(ResourceNames::RESOURCE_DATA, $ref_id),
				['title']
			),
			'destination_path' => ResourceTreeHandler::getPathFromId($ref_id),
		], strings: self::buildStrings());
	}
	public function canAccess(iTreeBuildContext $tree_build_context, WidgetConnection $connection): bool
	{
		return Roles::hasRole(RoleList::ROLE_FILES_ADMIN);
	}
}
