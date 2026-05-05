<?php

class EventJumploaderPartitionedUploadHandler extends AbstractEvent
{
	public function authorize(PolicyContext $policyContext): PolicyDecision
	{
		return $policyContext->principal->inGroup(Usergroups::SYSTEMUSERGROUP_LOGGEDIN)
			? PolicyDecision::allow()
			: PolicyDecision::deny();
	}

	public function run(): void
	{
		$ref_id = Request::_GET('ref_id');

		if (is_null($ref_id)) {
			$this->respondJson(400, false, t('cms.file.missing_ref_id'));
		}

		$temporary_uploaded_file = JumploaderUpload::manageUpload();

		if ($temporary_uploaded_file === false) {
			$this->respondJson(400, false, t('cms.file.upload_error'));
		}

		if ($temporary_uploaded_file === true) {
			$this->respondJson(200, true, '');
		}

		$file_id = FileContainer::addFile($temporary_uploaded_file);
		@unlink($temporary_uploaded_file);

		if ($file_id === false) {
			$this->respondJson(500, false, t('cms.file.upload_error'));
		}

		$add_result = $this->addResource((int) $ref_id, $file_id, (string) ($_FILES['file']['name'] ?? $_POST['fileName'] ?? ''));

		if (!$add_result->ok) {
			FileContainer::delFile($file_id);
			$this->respondJson(500, false, $add_result->error?->message ?? t('cms.file.upload_error'));
		}

		$this->respondJson(200, true, t('cms.file.uploaded'));
	}

	private function addResource(int $parent_id, int $file_id, string $save_name): ResourceTreeMutationResult
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

		return ResourceTreeHandler::addResourceEntryResult($savedata, $parent_id);
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
