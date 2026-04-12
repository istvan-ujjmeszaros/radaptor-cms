<?php

class EventDropzonePartitionedUploadHandler extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->hasRole(RoleList::ROLE_FILES_ADMIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$ref_id = Request::_GET('ref_id');

		if (is_null($ref_id)) {
			$this->respondJson(400, false, t('cms.file.missing_ref_id'));
		}

		$uploaded_file = DropzoneUpload::manageUpload();

		if ($uploaded_file === false) {
			$error_message = t('cms.file.upload_error');
			$debug_error = DropzoneUpload::getLastError();

			if (Config::DEV_APP_DEBUG_INFO->value() && is_string($debug_error) && $debug_error !== '') {
				$error_message = $debug_error;
			}

			$this->respondJson(400, false, $error_message);
		}

		if ($uploaded_file === true) {
			$this->respondJson(200, true, '');
		}

		$file_id = FileContainer::addFile($uploaded_file['path']);
		@unlink($uploaded_file['path']);

		if ($file_id === false) {
			SystemMessages::_error(t('cms.file.upload_error'));
			$this->respondJson(500, false, t('cms.file.upload_error'));
		}

		$page_id = $this->addResource((int) $ref_id, $file_id, $uploaded_file['original_name']);

		if ($page_id === false) {
			SystemMessages::_error(t('cms.file.upload_error'));
			$this->respondJson(500, false, t('cms.file.upload_error'));
		}

		SystemMessages::addSystemMessage(t('cms.file.uploaded'));
		$this->respondJson(200, true, t('cms.file.uploaded'));
	}

	private function addResource(int $parent_id, int $file_id, string $save_name): false|int
	{
		$mime = mime_content_type(FileContainer::realPathFromFileId($file_id));

		if (!$mime) {
			$mime = 'unknown';
		}

		$savedata = [
			'node_type' => 'file',
			'resource_name' => $save_name,
			'path' => ResourceTreeHandler::getPathFromId($parent_id),
			'file_id' => $file_id,
			'title' => $save_name,
			'mime' => $mime,
		];

		return ResourceTreeHandler::addResourceEntry($savedata, $parent_id);
	}

	private function respondJson(int $status_code, bool $ok, string $message): never
	{
		http_response_code($status_code);
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode([
			'ok' => $ok,
			'message' => $message,
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		exit;
	}
}
