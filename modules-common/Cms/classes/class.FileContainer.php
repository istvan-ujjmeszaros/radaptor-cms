<?php

abstract class FileContainer
{
	public const int MAX_FILES_IN_DIRECTORY = 998;
	public const int TOKEN_LENGTH = 8;
	public const int FIRST_STORAGE_FOLDER_ID = 1;

	/**
	 * Lekérdezi, hogy az utolsó indexű storage folder-ben mennyi fájl van
	 * (az adatbázis alapján), és ha még fér ide, akkor visszaadja ennek az
	 * indexét, különben pedig a következő indexet adja vissza.
	 *
	 * @return int Az aktuális feltöltési mappa indexe
	 */
	private static function _getNextStorageFolderId(): int
	{
		$query = "SELECT COUNT(1) AS counter, storage_folder_id FROM (SELECT DISTINCT(md5_hash), storage_folder_id FROM mediacontainer_vfs_files) th, (SELECT MAX(storage_folder_id) max_storage_folder_id FROM mediacontainer_vfs_files) ts WHERE storage_folder_id = ts.max_storage_folder_id";

		$stmt = Db::instance()->prepare($query);
		$stmt->execute();

		$rs = $stmt->fetch(PDO::FETCH_ASSOC);

		return $rs['counter'] < self::MAX_FILES_IN_DIRECTORY
			? $rs['storage_folder_id'] ?? self::FIRST_STORAGE_FOLDER_ID
			: ++$rs['storage_folder_id'];
	}

	/**
	 * Létrehozza az adott id-jű storage folder-t (ha még nem létezik).
	 *
	 * @param int $storage_folder_id
	 */
	private static function _createStorageFolder(int $storage_folder_id): void
	{
		$path = DEPLOY_ROOT . Config::PATH_UPLOADED_FILES_DIRECTORY->value() . '/' . $storage_folder_id . '/';

		if (!file_exists($path)) {
			@mkdir($path, Config::LINUX_FILE_MODE_DIRECTORY->value(), true);
		}

		self::_normalizeFilesystemPermissions($path, true);
	}

	/**
	 * visszaadja egy tárolt fájl teljes elérési útvonalát a hash (fájlnév) és
	 * a storage_folder_id alapján.
	 *
	 * @param string $md5_hash (32) $md5_hash
	 * @param int $storage_folder_id
	 * @return string
	 */
	public static function realPath(string $md5_hash, int $storage_folder_id): string
	{
		$path = DEPLOY_ROOT . Config::PATH_UPLOADED_FILES_DIRECTORY->value() . '/' . $storage_folder_id . '/' . $md5_hash;

		return str_replace('//', '/', $path);
	}

	/**
	 * visszaadja egy tárolt fájl teljes elérési útvonalát a file_id alapján.
	 *
	 * @param int $file_id
	 * @return string
	 */
	public static function realPathFromFileId(int $file_id): string
	{
		$file_data = FileContainer::getDataFromFileId($file_id);

		return FileContainer::realPath($file_data['md5_hash'], $file_data['storage_folder_id']);
	}

	/**
	 * Megadott útvonalon lévő fájlt rögzít az adatbázisba, és bemásol az
	 * __upload_folder__ megfelelő almappájába, de csak akkor, ha korábban nem
	 * került feltöltésre (md5_file alapján).
	 *
	 * @param string $uploaded_filename
	 * @return false|int ID of the uploaded file
	 */
	public static function addFile(string $uploaded_filename): false|int
	{
		if (!file_exists($uploaded_filename)) {
			return false;
		}

		$md5_hash = md5_file($uploaded_filename);
		$file_data = FileContainer::getDataFromMd5Hash($md5_hash);

		if (is_array($file_data)) {
			unset($file_data['file_id']);

			return DbHelper::insertHelper('mediacontainer_vfs_files', $file_data);
		}

		$storage_folder_id = FileContainer::_getNextStorageFolderId();
		$filesize = filesize($uploaded_filename);

		Db::instance()->beginTransaction();

		$savedata = [
			'md5_hash' => $md5_hash,
			'storage_folder_id' => $storage_folder_id,
			'filesize' => $filesize,
		];

		$last_id = DbHelper::insertHelper('mediacontainer_vfs_files', $savedata);

		// így csak a fájlfeltöltésekkor ellenőrizzük a storage_folder meglétét,
		// tehát lekéréskor nem lesznek felesleges (és nagyon lassú) ellenőrzések
		FileContainer::_createStorageFolder($storage_folder_id);

		if (!@copy($uploaded_filename, FileContainer::realPath($md5_hash, $storage_folder_id))) {
			Db::instance()->rollback();

			return false;
		}

		self::_normalizeFilesystemPermissions(FileContainer::realPath($md5_hash, $storage_folder_id), false);

		Db::instance()->commit();

		Cache::flush();

		return $last_id;
	}

	/**
	 * Adott azonosítójú fájlt töröl az adatbázisból, és ha az adott fájlra már
	 * nem hivatkozik több bejegyzés, akkor magát a tárolt fájlt is törli.
	 *
	 * @param int $file_id
	 * @return boolean
	 */
	public static function delFile(int $file_id): bool
	{
		$file_data = self::getDataFromFileId($file_id);

		if (!is_array($file_data)) {
			return false;
		}

		$return = DbHelper::deleteHelper('mediacontainer_vfs_files', $file_id) > 0;

		$same_files = self::getDataFromMd5Hash($file_data['md5_hash']);

		if ($same_files === false) {
			// törölhetjük, mert erre a fájlra már csak ez a bejegyzés hivatkozott
			$realPath = self::realPath($file_data['md5_hash'], $file_data['storage_folder_id']);

			if (file_exists($realPath)) {
				@unlink($realPath);
			}
		}

		return $return;
	}

	/**
	 * Fájl adatait adja vissza file_id alapján.
	 *
	 * @param int $file_id
	 * @return false|array
	 */
	public static function getDataFromFileId(int $file_id): false|array
	{
		$stmt = Db::instance()->prepare("SELECT * FROM mediacontainer_vfs_files WHERE file_id=? LIMIT 1");
		$stmt->execute([$file_id]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
	}

	/**
	 * Fájl adatait adja vissza md5_hash alapján.
	 *
	 * @param string $md5_hash (32) $md5_hash
	 * @return false|array
	 */
	public static function getDataFromMd5Hash(string $md5_hash): false|array
	{
		$stmt = Db::instance()->prepare("SELECT * FROM mediacontainer_vfs_files WHERE md5_hash=? LIMIT 1");
		$stmt->execute([$md5_hash]);

		return $stmt->fetch(PDO::FETCH_ASSOC);
	}

	private static function _normalizeFilesystemPermissions(string $path, bool $is_directory): void
	{
		if (!file_exists($path)) {
			return;
		}

		@chmod(
			$path,
			$is_directory ? Config::LINUX_FILE_MODE_DIRECTORY->value() : Config::LINUX_FILE_MODE->value()
		);
	}

	public static function forceDownload($file_id, $resize, $savename): void
	{
		$stmt = Db::instance()->prepare("SELECT * FROM mediacontainer_vfs_files WHERE file_id=? LIMIT 1");
		$stmt->execute([$file_id]);

		$rs = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($rs === false || count($rs) == 0) {
			return;
		}

		$savename = str_replace('"', "'", $savename);

		$path = FileContainer::realPathFromFileId($file_id);

		if (!is_readable($path)) {
			Kernel::abort("File not readable: " . $path);
		}

		if ($resize !== '') {
			$path = self::_GetRewrittenPath($resize, $path, $savename);
		}

		// IE miatt kell konvertálni...
		$savename = iconv("UTF-8", "ISO-8859-2//TRANSLIT", $savename);

		ResourceTreeHandler::setDownloadHeader(filesize($path), $savename);

		readfile("$path");

		exit;
	}

	private static function _GetRewrittenPath($resize, $originalPath, $savename)
	{
		$pathinfo = pathinfo((string) $savename);
		$exploded_size = explode('x', (string) $resize);

		// szabad méretezés
		if (count($exploded_size) == 2) {
			return $originalPath
				|> ImageManipulator::stretch(
					maxWidth: (int) $exploded_size[0],
					maxHeight: (int) $exploded_size[1],
					outputFormat: mb_strtolower($pathinfo['extension']),
					quality: 90
				)
				|> ImageManipulator::cache(cacheSubdirectoryName: 'resized');
		}

		// előre definiált manipuláció
		$predefinedImage = PredefinedImageHandler::factory($resize, $originalPath, $savename);

		if (is_null($predefinedImage)) {
			return $originalPath;
		}

		return $predefinedImage->getPathForManipulatedImage();
	}

	public static function viewInline($file_id, $resize, $savename, $mime): void
	{
		$stmt = Db::instance()->prepare("SELECT * FROM mediacontainer_vfs_files WHERE file_id=? LIMIT 1");
		$stmt->execute([$file_id]);

		$rs = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($rs === false || count($rs) == 0) {
			return;
		}

		$savename = str_replace('"', "'", $savename);

		$path = FileContainer::realPathFromFileId($file_id);

		if (!is_readable($path)) {
			Kernel::abort("File not readable: " . $path);
		}

		// IE miatt kell konvertálni...
		$savename = iconv("UTF-8", "ISO-8859-2//TRANSLIT", $savename);

		if ($resize !== '') {
			$path = self::_GetRewrittenPath($resize, $path, $savename);
		}

		ResourceTreeHandler::setInlineHeader(filesize($path), $savename, $mime);

		readfile($path);

		exit;
	}
}
