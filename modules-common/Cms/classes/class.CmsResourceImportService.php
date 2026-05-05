<?php

declare(strict_types=1);

class CmsResourceImportService
{
	/**
	 * @return array{
	 *     resource_id: int,
	 *     file_id: int,
	 *     path: string,
	 *     mime: string,
	 *     replaced: bool
	 * }
	 */
	public static function importFile(string $source_path, string $target_folder, string $resource_name, string $title = ''): array
	{
		$file_id = null;

		try {
			$source = CmsMigrationSourcePathHelper::resolveReadableFile($source_path);
			$folder_path = CmsPathHelper::splitFolderPath($target_folder)['normalized_path'];
			$existing = ResourceTreeHandler::getResourceTreeEntryData($folder_path, $resource_name);
			$old_file_id = null;

			if (is_array($existing) && ($existing['node_type'] ?? null) !== 'file') {
				throw new RuntimeException(t('cms.resource.error.path_exists_not_file', ['path' => $folder_path . $resource_name]));
			}

			if (is_array($existing)) {
				$attributes = AttributeHandler::getAttributes(
					new AttributeResourceIdentifier(ResourceNames::RESOURCE_DATA, (string) (int) $existing['node_id'])
				);
				$old_file_id = is_numeric($attributes['file_id'] ?? null) ? (int) $attributes['file_id'] : null;
			}

			$folder_id = CmsResourceSpecService::upsertFolder(['path' => $target_folder]);
			$folder = ResourceTreeHandler::getResourceTreeEntryDataById($folder_id);

			if (!is_array($folder)) {
				throw new RuntimeException(t('cms.resource.error.target_folder_not_found', ['path' => $target_folder]));
			}

			$file_id = FileContainer::addFile($source);

			if (!is_int($file_id) || $file_id <= 0) {
				throw new RuntimeException(t('cms.resource.error.import_file_failed', ['path' => $source_path]));
			}

			$stored_file = FileContainer::realPathFromFileId($file_id);
			$mime = is_readable($stored_file) ? (mime_content_type($stored_file) ?: 'application/octet-stream') : 'application/octet-stream';
			$save_data = [
				'node_type' => 'file',
				'resource_name' => $resource_name,
				'path' => $folder_path,
				'file_id' => $file_id,
				'title' => $title !== '' ? $title : $resource_name,
				'mime' => $mime,
			];
			$replaced = false;
			$response_file_id = $file_id;

			if (is_array($existing)) {
				$update_result = ResourceTreeHandler::updateResourceTreeEntryResult($save_data, (int) $existing['node_id']);

				if (!$update_result->ok) {
					// Import is a batch boundary; callers render this item failure at the event or CLI layer.
					throw new RuntimeException($update_result->error?->message ?? t('cms.resource.error.update_failed_for_path', ['path' => $folder_path . $resource_name]));
				}

				$resource_id = (int) $existing['node_id'];
				$replaced = true;
				$file_id = null;

				if ($old_file_id !== null
					&& $old_file_id > 0
					&& $old_file_id !== $response_file_id
					&& !ResourceTreeHandler::hasResourceReferencesForFileId($old_file_id)
					&& FileContainer::getDataFromFileId($old_file_id) !== false) {
					FileContainer::delFile($old_file_id);
				}
			} else {
				$add_result = ResourceTreeHandler::addResourceEntryResult($save_data, (int) $folder['node_id']);
				$resource_id = $add_result->ok ? (int) $add_result->data : null;

				if (!is_int($resource_id) || $resource_id <= 0) {
					FileContainer::delFile($file_id);
					$file_id = null;

					// Import is a batch boundary; callers render this item failure at the event or CLI layer.
					throw new RuntimeException($add_result->error?->message ?? t('cms.resource.error.create_failed_for_path', ['path' => $folder_path . $resource_name]));
				}

				$file_id = null;
			}

			return [
				'resource_id' => $resource_id,
				'file_id' => $response_file_id,
				'path' => ResourceTreeHandler::getPathFromId($resource_id),
				'mime' => $mime,
				'replaced' => $replaced,
			];
		} catch (Throwable $exception) {
			if (is_int($file_id) && $file_id > 0) {
				FileContainer::delFile($file_id);
			}

			throw $exception;
		}
	}
}
