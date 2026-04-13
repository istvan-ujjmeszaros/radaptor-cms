<?php

class JumploaderUpload
{
	private readonly string $_upload_temp_dir;
	private readonly string $_upload_partitions_dir;
	private readonly int $_file_id;
	private readonly int $_partition_index;
	private readonly int $_partition_count;
	private readonly int $_file_length;
	private readonly string $_client_id;
	private readonly string $_source_file_path;
	private readonly string $_target_file_path;
	private bool $_all_partitions_ready = false;

	public function __construct()
	{
		$this->_upload_temp_dir = DEPLOY_ROOT . Config::PATH_UPLOADING_TEMPORARY_DIRECTORY->value();
		$this->_upload_partitions_dir = DEPLOY_ROOT . Config::PATH_UPLOADING_TEMPORARY_PARTITIONS_DIRECTORY->value();

		$this->_file_id = $_POST['fileId'];
		$this->_partition_index = $_POST['partitionIndex'];
		$this->_partition_count = $_POST['partitionCount'];
		$this->_file_length = $_POST['fileLength'];

		$this->_client_id = 'partition';

		$this->_source_file_path = $_FILES['file']['tmp_name'];
		$this->_target_file_path = $this->_upload_partitions_dir . $this->_client_id . "." . $this->_file_id . "." . $this->_partition_index;
	}

	public static function manageUpload(): bool|string
	{
		$jumploader = new JumploaderUpload();

		if (!$jumploader->uploadPartition()) {
			return false;
		}

		if (!$jumploader->checkPartitions()) {
			return false;
		}

		if ($jumploader->_all_partitions_ready) {
			return $jumploader->rebuildPartitionedFile();
		}

		return true;
	}

	public function uploadPartition(): bool
	{
		if (!move_uploaded_file($this->_source_file_path, $this->_target_file_path)) {
			return false;
		}

		return true;
	}

	public function checkPartitions(): bool
	{
		$this->_all_partitions_ready = true;

		$partitions_length = 0;

		for ($i = 0; $this->_all_partitions_ready && $i < $this->_partition_count; $i++) {
			$partition_file = $this->_upload_partitions_dir . $this->_client_id . "." . $this->_file_id . "." . $i;

			if (file_exists($partition_file)) {
				$partitions_length += filesize($partition_file);
			} else {
				$this->_all_partitions_ready = false;
			}
		}

		if ($this->_partition_index == $this->_partition_count - 1 && (!$this->_all_partitions_ready || $partitions_length != intval($this->_file_length))) {
			return false;
		}

		return true;
	}

	public function rebuildPartitionedFile(): false|string
	{
		if (!$this->_all_partitions_ready) {
			return false;
		}

		$rebuilt_file_name = $this->_upload_temp_dir . $_POST['fileName'];

		if (file_exists($rebuilt_file_name)) {
			$path_parts = pathinfo($rebuilt_file_name);

			$i = 0;

			do {
				++$i;
				$new_file_name = $path_parts['dirname'] . '/' . $path_parts['filename'] . '(' . $i . ').' . $path_parts['extension'];
			} while (file_exists($new_file_name));

			$rebuilt_file_name = $new_file_name;
		}

		$rebuilt_file_handle = fopen($rebuilt_file_name, 'a');

		for ($i = 0; $i < $this->_partition_count; $i++) {
			$partition_file_name = $this->_upload_partitions_dir . $this->_client_id . "." . $this->_file_id . "." . $i;
			$partition_file_handle = fopen($partition_file_name, "rb");

			$contents = fread($partition_file_handle, filesize($partition_file_name));
			fclose($partition_file_handle);

			fwrite($rebuilt_file_handle, $contents);

			unlink($partition_file_name);
		}

		fclose($rebuilt_file_handle);

		return $rebuilt_file_name;
	}
}
