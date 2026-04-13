<?php

class DropzoneUpload
{
	private static ?string $_last_error = null;

	private readonly string $_upload_temp_dir;
	private readonly string $_upload_partitions_dir;
	private readonly string $_file_name;
	private readonly string $_file_id;
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

		$this->_file_name = self::sanitizeFileName((string) ($_FILES['file']['name'] ?? ''));
		$this->_file_length = (int) ($_POST['dztotalfilesize'] ?? ($_FILES['file']['size'] ?? 0));
		$this->_file_id = $this->resolveFileId();
		$this->_partition_index = (int) ($_POST['dzchunkindex'] ?? 0);
		$this->_partition_count = (int) ($_POST['dztotalchunkcount'] ?? 1);

		$this->_client_id = 'partition';
		$this->_source_file_path = (string) ($_FILES['file']['tmp_name'] ?? '');
		$this->_target_file_path = $this->_upload_partitions_dir . $this->_client_id . '.' . $this->_file_id . '.' . $this->_partition_index;
	}

	/**
	 * @return array{path: string, original_name: string}|bool
	 */
	public static function manageUpload(): array|bool
	{
		self::$_last_error = null;
		$dropzone = new DropzoneUpload();

		if (!$dropzone->isValidRequest()) {
			return false;
		}

		if (!$dropzone->uploadPartition()) {
			return false;
		}

		if (!$dropzone->checkPartitions()) {
			return false;
		}

		if (!$dropzone->_all_partitions_ready) {
			return true;
		}

		$rebuilt_file_path = $dropzone->rebuildPartitionedFile();

		if ($rebuilt_file_path === false) {
			return false;
		}

		return [
			'path' => $rebuilt_file_path,
			'original_name' => $dropzone->_file_name,
		];
	}

	public static function getLastError(): ?string
	{
		return self::$_last_error;
	}

	private function isValidRequest(): bool
	{
		if ($this->_file_id === '' || $this->_file_name === '') {
			return self::fail('Dropzone upload request is missing file metadata.');
		}

		if ($this->_partition_index < 0 || $this->_partition_count < 1 || $this->_file_length < 0) {
			return self::fail('Dropzone upload request has invalid chunk metadata.');
		}

		if ($this->_source_file_path === '' || !is_uploaded_file($this->_source_file_path)) {
			return self::fail('Dropzone upload request does not contain a valid uploaded file.');
		}

		return $this->ensureUploadDirectories();
	}

	private function ensureUploadDirectories(): bool
	{
		if (!self::ensureDirectory($this->_upload_temp_dir) || !self::ensureDirectory($this->_upload_partitions_dir)) {
			return self::fail('Dropzone upload temporary directories could not be created.');
		}

		if (!is_writable($this->_upload_temp_dir) || !is_writable($this->_upload_partitions_dir)) {
			return self::fail('Dropzone upload temporary directories are not writable.');
		}

		return true;
	}

	private static function ensureDirectory(string $directory): bool
	{
		if (is_dir($directory)) {
			return true;
		}

		if (@mkdir($directory, 0o775, true) || is_dir($directory)) {
			return true;
		}

		return false;
	}

	private function uploadPartition(): bool
	{
		if (@move_uploaded_file($this->_source_file_path, $this->_target_file_path)) {
			return true;
		}

		return self::fail('Uploaded chunk could not be moved into the temporary partitions directory.');
	}

	private function checkPartitions(): bool
	{
		$this->_all_partitions_ready = true;
		$partitions_length = 0;

		for ($i = 0; $this->_all_partitions_ready && $i < $this->_partition_count; $i++) {
			$partition_file = $this->_upload_partitions_dir . $this->_client_id . '.' . $this->_file_id . '.' . $i;

			if (file_exists($partition_file)) {
				$partitions_length += (int) filesize($partition_file);
			} else {
				$this->_all_partitions_ready = false;
			}
		}

		if (
			$this->_partition_index === $this->_partition_count - 1
			&& (!$this->_all_partitions_ready || $partitions_length !== $this->_file_length)
		) {
			return self::fail('Uploaded chunks are incomplete or their combined size does not match the original file.');
		}

		return true;
	}

	private function rebuildPartitionedFile(): false|string
	{
		if (!$this->_all_partitions_ready) {
			return false;
		}

		$rebuilt_file_name = self::buildUniqueFilePath($this->_upload_temp_dir, $this->_file_name);
		$rebuilt_file_handle = fopen($rebuilt_file_name, 'wb');

		if ($rebuilt_file_handle === false) {
			return self::fail('The rebuilt upload file could not be opened for writing.');
		}

		for ($i = 0; $i < $this->_partition_count; $i++) {
			$partition_file_name = $this->_upload_partitions_dir . $this->_client_id . '.' . $this->_file_id . '.' . $i;
			$partition_file_handle = fopen($partition_file_name, 'rb');

			if ($partition_file_handle === false) {
				fclose($rebuilt_file_handle);
				@unlink($rebuilt_file_name);

				return self::fail('One of the uploaded chunk files could not be reopened during rebuild.');
			}

			$copy_result = stream_copy_to_stream($partition_file_handle, $rebuilt_file_handle);
			fclose($partition_file_handle);

			if ($copy_result === false) {
				fclose($rebuilt_file_handle);
				@unlink($rebuilt_file_name);

				return self::fail('One of the uploaded chunk files could not be copied into the rebuilt upload.');
			}

			@unlink($partition_file_name);
		}

		fclose($rebuilt_file_handle);

		return $rebuilt_file_name;
	}

	private static function sanitizeFileName(string $file_name): string
	{
		$file_name = str_replace("\0", '', $file_name);
		$file_name = basename(str_replace('\\', '/', $file_name));

		return $file_name !== '' ? $file_name : 'upload.bin';
	}

	private function resolveFileId(): string
	{
		$dzuuid = preg_replace('/[^A-Za-z0-9._-]/', '', (string) ($_POST['dzuuid'] ?? '')) ?? '';

		if ($dzuuid !== '') {
			return $dzuuid;
		}

		$fallback_seed = implode('|', [
			session_id(),
			$this->_file_name,
			(string) $this->_file_length,
		]);

		return hash('sha256', $fallback_seed);
	}

	private static function buildUniqueFilePath(string $directory, string $file_name): string
	{
		$directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		$candidate = $directory . $file_name;

		if (!file_exists($candidate)) {
			return $candidate;
		}

		[$base_name, $extension] = self::splitFileName($file_name);

		for ($i = 1; ; $i++) {
			$candidate = $directory . $base_name . '(' . $i . ')';

			if ($extension !== '') {
				$candidate .= '.' . $extension;
			}

			if (!file_exists($candidate)) {
				return $candidate;
			}
		}
	}

	/**
	 * @return array{0: string, 1: string}
	 */
	private static function splitFileName(string $file_name): array
	{
		$last_dot = strrpos($file_name, '.');

		if ($last_dot === false || $last_dot === 0) {
			return [$file_name, ''];
		}

		return [
			substr($file_name, 0, $last_dot),
			substr($file_name, $last_dot + 1),
		];
	}

	private static function fail(string $message): bool
	{
		self::$_last_error = $message;

		return false;
	}
}
