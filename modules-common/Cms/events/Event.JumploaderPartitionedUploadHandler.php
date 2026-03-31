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
			SystemMessages::_error(t('cms.file.missing_ref_id'));

			return;
		}

		$temporary_uploaded_file = JumploaderUpload::manageUpload();

		if ($temporary_uploaded_file === false) {
			return;
		}

		if ($temporary_uploaded_file === true) {
			return;
		}

		// 1. Beszúrjuk a fájl tárolóba
		$file_id = FileContainer::addFile($temporary_uploaded_file);
		@unlink($temporary_uploaded_file);

		if ($file_id === false) {
			SystemMessages::_error(t('cms.file.upload_error'));

			return;
		}

		// 2. Rögzítjük a weboldal fában
		$this->addResource((int)$ref_id, $file_id, $_FILES['file']['name']);
	}

	private function addResource($parent_id, $file_id, $savename): void
	{
		$mime = mime_content_type(FileContainer::realPathFromFileId($file_id));

		if (!$mime) {
			$mime = 'unknown';
		}

		$savedata = [
			'node_type' => 'file',
			'resource_name' => $savename,
			'path' => ResourceTreeHandler::getPathFromId($parent_id),
			'file_id' => $file_id,
			'title' => $savename,
			'mime' => $mime,
		];

		$page_id = ResourceTreeHandler::addResourceEntry($savedata, $parent_id);

		if ($page_id) {
			SystemMessages::addSystemMessage(t('cms.file.uploaded'));
		} else {
			SystemMessages::addSystemMessage(t('cms.file.upload_error'));
		}
	}
}
