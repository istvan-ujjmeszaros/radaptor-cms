<?php

/**
 * Kép átméretezését, vízjelezését, maszkolását végzi el a beállított
 * paraméterek alapján.
 * Az osztály példányosításakor megnézi, hogy az adott képből már
 * létezik-e a kívánt módosított változat (ha fájlba kell menteni,akkor
 * azt vizsgálja, hogy létezik-e már a kért fájl, és hogy ennek az integritása
 * érvényes-e, ha pedig a böngésző a kimenet, akkor megnézi, hogy van-e
 * cache-elt fájl, és érvényes-e az integritása. Ha szükséges, akkor az
 * osztály elvégzi a kért módosításokat, és menti a módosított verziót a
 * kívánt helyre, vagy menti a cache könyvtárba és megjeleníti a cache-elt
 * verziót.
 *
 * @access public
 * @author István Ujj-Mészáros, <istvan@radaptor.com>
 */
class ImageHandler
{
	/**
	 * Cache fájlnév generálásához használt beállítások.
	 */

	public const int FILENAME_FILESIZE_MD5LENGTH = 8;
	public const int FILENAME_PARAMS_MD5LENGTH = 12;

	/**
	 * Hibaüzenetek.
	 */
	public const string ERROR_NO_ERROR = '';
	public const string ERROR_MISSING_SOURCE = 'source file missing';
	public const string ERROR_BAD_MIME = 'source has bad mime type';
	public const string ERROR_OPENING_BAD_MIME = 'source (open) has bad mime type';
	public const string ERROR_NOT_VALID_IMAGE = 'source is not a valid image file';
	public const string ERROR_WHILE_OPENING_IMAGE = 'error while opening source image';
	public const string ERROR_SAVE_ERROR = 'error while copying cache file to save file';
	public const string ERROR_TARGET_DIRECTORY_NOT_EXISTS = 'target directory not exists';
	public const string ERROR_SAVEFILE_ALREADY_EXISTS = 'savefile already exists';
	public const string ERROR_CANNOT_CREATE_CACHE_SUBDIRECTORY = 'can not create cache subdirectory';
	public const string ERROR_CANNOT_DELETE_CORRUPTED_CACHE_FILE = 'can not delete corrupted cache file';

	/**
	 * Hiba esetén a hibaüzenetet tartalmazza (csak egyet).
	 *
	 * @var string Hibaüzenet
	 */
	public string $error = self::ERROR_NO_ERROR;

	/**
	 * A bemeneti kép MIME típusa.
	 *
	 * @access protected
	 * @var string
	 */
	protected string $_inputMime = '';

	/**
	 * A generateFilename függvény által generált fájlnév.
	 *
	 * @var string
	 */
	protected string $_generatedFilename = '';

	/**
	 * A cache fájl abszolút mentési helye.
	 *
	 * @var string
	 */
	protected string $_cacheFileAbsolutePath = '';

	/**
	 * A cache fájl relatív útvonala.
	 *
	 * @var string
	 */
	protected string $_cacheFileRelativePath = '';
	private bool $_checked = false;

	/** @var GdImage|null $image_resource */
	public ?GdImage $image_resource = null;
	public ?array $imageInfo = null;
	private ?array $_original_imageInfo = null;
	public bool $alreadyCached = false;
	public static array $max_size = [
		'width' => 0,
		'height' => 0,
	];

	private int $finalWidth;

	private int $finalHeight;

	/**
	 * Megnyitja az adott képet, elvégzi a predefinedSettings alapján kért
	 * műveleteket, majd cache-be menti az elkészült képet, és
	 * beállítja a cache-elt verzióra mutató attribútumot (vagy ha már
	 * létezett a cache-elt verzió, akkor az alapján állítja be
	 * a cache-elt verzióra mutató attribútumot).
	 *
	 * @access public
	 * @param string $inputFile
	 * @param array $_parameters
	 * @param string $_cacheSubdirectory
	 * @param bool $cachePathUseFilename
	 * @param array $_cacheNameGeneratorExtraParameters
	 */
	public function __construct(
		private readonly string $inputFile,
		protected array        $_parameters,
		protected string       $_cacheSubdirectory,
		private readonly bool  $cachePathUseFilename = true,
		private readonly array $_cacheNameGeneratorExtraParameters = []
	) {
		$this->checkIsCachedVersionExists();
	}

	/**
	 * Beállítjuk a paramétereket a kapott tömb alapján.
	 */
	public static function setParameters(array $new_parameters, array &$parameters): void
	{
		foreach ($new_parameters as $parameter => $value) {
			if (isset($parameters[$parameter])) {
				$parameters[$parameter] = $value;
			} else {
				Kernel::abort("unknown imageManipulator parameter: <i>$parameter</i>");
			}
		}

		// ABC sorrendbe rendezzük a paramétereket, hogy a cache generálásnál
		// ne számítson, hogy milyen sorrendben vannak megadva a dolgok
		//		self::ksortRecursive($parameters);
	}

	/**
	 * Fájlnevet generál az eredeti fájl tulajdonságai és a módosítási
	 * attribútumok alapján.
	 *
	 * @access protected
	 * @return void
	 */
	protected function _generateCacheFilename(): void
	{
		if ($this->error) {
			return;
		}

		//		$basename = basename($this->inputFile);
		$pathinfo = pathinfo(basename($this->inputFile));

		// nem kell, mert a fájlnév bekerül az útvonalba (robbantva)
		//		$md5_source = mb_substr(md5($basename), 0, self::FILENAME_SOURCE_MD5LENGTH);
		$filesize = @filesize($this->inputFile);

		if ($filesize === false) {
			$this->error = self::ERROR_MISSING_SOURCE . " ($this->inputFile)";

			return;
		}

		if (isset($this->_parameters['cachefilename_postfix'])) {
			$cachefilename_postfix = $this->_parameters['cachefilename_postfix'];
			unset($this->_parameters['cachefilename_postfix']);
			$filesize = 666;
		} else {
			$cachefilename_postfix = '';
		}

		$md5_filesize = mb_substr(md5((string)$filesize), 0, self::FILENAME_FILESIZE_MD5LENGTH);
		$md5_params = md5((count($this->_cacheNameGeneratorExtraParameters) > 0 ? serialize([
			$this->_parameters,
			$this->_cacheNameGeneratorExtraParameters,
		]) : serialize($this->_parameters)));
		$md5_params_stripped = mb_substr($md5_params, 0, self::FILENAME_PARAMS_MD5LENGTH);

		$this->_generatedFilename = $md5_filesize . $md5_params_stripped . $cachefilename_postfix . '.' . $this->_parameters['outputFormat'];

		//$subfoldername = ContentPath::explodeFilename($pathinfo['filename']);
		$subfoldername = $pathinfo['filename'];

		if ($this->cachePathUseFilename) {
			$this->_cacheFileAbsolutePath = str_replace('//', '/', DEPLOY_ROOT . Config::PATH_IMAGE_CACHE_LOCAL_SUBFOLDER->value() . $subfoldername . '/' . $this->_cacheSubdirectory . '/' . $this->_generatedFilename);
			$this->_cacheFileRelativePath = str_replace('//', '/', Config::PATH_IMAGE_CACHE_URL->value() . $subfoldername . '/' . $this->_cacheSubdirectory . '/' . $this->_generatedFilename);
		} else {
			$this->_cacheFileAbsolutePath = str_replace('//', '/', DEPLOY_ROOT . Config::PATH_IMAGE_CACHE_LOCAL_SUBFOLDER->value() . $this->_cacheSubdirectory . '/' . $this->_generatedFilename);
			$this->_cacheFileRelativePath = str_replace('//', '/', Config::PATH_IMAGE_CACHE_URL->value() . $this->_cacheSubdirectory . '/' . $this->_generatedFilename);
		}

		$this->_cacheFileAbsolutePath = str_replace('http:/', 'https://', $this->_cacheFileAbsolutePath);
		$this->_cacheFileRelativePath = str_replace('http:/', 'https://', $this->_cacheFileRelativePath);
	}

	/**
	 * Ellenőrzi, hogy létezik-e az átméretezni kívánt forrásfájl, és hogy
	 * támogatott formátumú képfájl-e.
	 *
	 * @param string $image_file
	 * @return bool
	 */
	protected function _checkImageFile(string $image_file): bool
	{
		if ($this->_checked) {
			return true;
		}

		$this->_checked = true;

		if ($this->error) {
			return false;
		}

		$size = @getimagesize($image_file);

		if ($size === false) {
			if (!file_exists($image_file)) {
				$this->error = self::ERROR_MISSING_SOURCE;

				return false;
			}
			$this->error = self::ERROR_NOT_VALID_IMAGE;

			return false;
		}

		$valid_mimes = [
			'image/jpeg',
			'image/pjpeg',
			'image/gif',
			'image/png',
			'image/wbmp',
			'image/bmp',
		];
		$mime = $size['mime'];

		if (!in_array($mime, $valid_mimes)) {
			$this->error = self::ERROR_BAD_MIME . "($mime)";

			return false;
		}

		$this->_inputMime = $mime;

		return true;
	}

	public function getError(): string
	{
		return $this->error;
	}

	public function openImageFile(): ?GdImage
	{
		$this->_checkImageFile($this->inputFile);

		switch ($this->_inputMime) {
			case "image/jpeg":
			case "image/pjpeg":

				try {
					$this->image_resource = @ImageCreateFromJpeg($this->inputFile);
				} catch (Exception) {
					$this->error = self::ERROR_WHILE_OPENING_IMAGE;

					return null;
				}

				if (!$this->image_resource) {
					$this->error = self::ERROR_WHILE_OPENING_IMAGE;

					return null;
				}

				break;

			case "image/gif":

				$this->image_resource = @ImageCreateFromGif($this->inputFile);

				if (!$this->image_resource) {
					$this->error = self::ERROR_WHILE_OPENING_IMAGE;

					return null;
				}

				break;

			case "image/png":

				$this->image_resource = @ImageCreateFromPng($this->inputFile);

				if ($this->image_resource === false) {
					$this->error = self::ERROR_WHILE_OPENING_IMAGE;

					return null;
				}

				break;

			case "image/wbmp":
			case "image/bmp":

				$this->image_resource = self::ImageCreateFromBMP($this->inputFile);

				if (is_null($this->image_resource)) {
					$this->error = self::ERROR_WHILE_OPENING_IMAGE;

					return null;
				}

				break;

			default:

				$this->error = self::ERROR_OPENING_BAD_MIME . ": ($this->_inputMime)";

				return null;
		}

		self::setAlphaBlending($this->image_resource);

		return $this->image_resource;
	}

	public static function setAlphaBlending(GdImage $resource_image): void
	{
		imagealphablending($resource_image, false);
		imagesavealpha($resource_image, true);
	}

	public static function disableAlphaBlending(GdImage $resource_image): void
	{
		imagesavealpha($resource_image, false);
		imagealphablending($resource_image, true);
	}

	public static function setProperBgColor(GdImage $imageResource, array $parameters): false|int
	{
		if (isset($parameters['background-color'])) {
			$parameters['bgColor'] = $parameters['background-color'];
		}

		if (isset($parameters['background-opacity'])) {
			$parameters['bgOpacity'] = $parameters['background-opacity'];
		}

		if (is_array($parameters['bgColor']) && count($parameters['bgColor']) == 3 && isset($parameters['bgColor'][0]) && isset($parameters['bgColor'][1]) && isset($parameters['bgColor'][2])) {
			// --- COLOR SPECIFIED ---
			switch ($parameters['outputFormat']) {
				case 'png':

					$backgroundColor = imagecolorallocatealpha($imageResource, $parameters['bgColor'][0], $parameters['bgColor'][1], $parameters['bgColor'][2], $parameters['bgOpacity']);
					self::setAlphaBlending($imageResource);

					imagefill($imageResource, 0, 0, $backgroundColor);

					break;

				case 'gif':

					$backgroundColor = imagecolorallocate($imageResource, $parameters['bgColor'][0], $parameters['bgColor'][1], $parameters['bgColor'][2]);

					if ($parameters['bgOpacity'] == 127) {
						$backgroundColor = imagecolortransparent($imageResource, $backgroundColor);
					}

					self::disableAlphaBlending($imageResource);

					imagefill($imageResource, 0, 0, $backgroundColor);

					break;

				case 'jpg':
				default:

					// fehérre állítjuk a hátteret
					$backgroundColor = imagecolorallocate($imageResource, $parameters['bgColor'][0], $parameters['bgColor'][1], $parameters['bgColor'][2]);

					self::disableAlphaBlending($imageResource);

					imagefill($imageResource, 0, 0, $backgroundColor);

					break;
			}
		} else {
			// --- TRANSPARENT or WHITE---
			switch ($parameters['outputFormat']) {
				case 'png':

					if ($parameters['bgColor'] === 'transparent') {
						$backgroundColor = imagecolorallocatealpha($imageResource, 255, 255, 255, 126);
					} else {
						$backgroundColor = imagecolorallocatealpha($imageResource, 255, 255, 255, ($parameters['bgOpacity'] ?? 126));
					}

					self::setAlphaBlending($imageResource);

					imagefill($imageResource, 0, 0, $backgroundColor);

					break;

				case 'gif':

					$backgroundColor = imagecolorallocate($imageResource, 255, 255, 255);
					$backgroundColor = imagecolortransparent($imageResource, $backgroundColor);

					self::disableAlphaBlending($imageResource);

					imagefill($imageResource, 0, 0, $backgroundColor);

					break;

				case 'jpg':
				default:

					// fehérre állítjuk a hátteret
					$backgroundColor = imagecolorallocate($imageResource, 255, 255, 255);

					self::disableAlphaBlending($imageResource);

					imagefill($imageResource, 0, 0, $backgroundColor);

					break;
			}
		}

		return $backgroundColor;
	}

	/**
	 * Megnézi, hogy van-e cache-elt verzió a képből, és ha van, akkor
	 * ezt adja vissza a kimenetre.
	 *
	 * @access protected
	 * @return bool
	 */
	public function checkIsCachedVersionExists(): bool
	{
		if ($this->error) {
			return false;
		}

		$this->_generateCacheFilename();

		$image_info = false;

		if (is_readable($this->_cacheFileAbsolutePath)) {
			$image_info = @getimagesize($this->_cacheFileAbsolutePath);
		}

		if ($image_info !== false) {
			$this->imageInfo = $image_info;
		} else {
			$this->imageInfo = null;
		}

		if (is_null($this->imageInfo)) {
			return false;
		}

		$this->finalWidth = $this->imageInfo[0];
		$this->finalHeight = $this->imageInfo[1];

		$this->alreadyCached = true;

		return true;
	}

	/**
	 * Cache fájlba írja a módosított, elkészült képet.
	 *
	 * @access protected
	 * @return false|string
	 */
	public function writeCachedVersion(): false|string
	{
		if ($this->error) {
			return false;
		}

		$path = dirname($this->_cacheFileAbsolutePath);

		if (!file_exists($path) && !self::makePath($path)) {
			$this->error = self::ERROR_CANNOT_CREATE_CACHE_SUBDIRECTORY . "`$path`";

			return false;
		}

		match ($this->_parameters['outputFormat']) {
			'jpg' => @imagejpeg($this->image_resource, $this->_cacheFileAbsolutePath, $this->_parameters['quality']),
			'png' => @imagepng($this->image_resource, $this->_cacheFileAbsolutePath, 9),
			'gif' => @imagegif($this->image_resource, $this->_cacheFileAbsolutePath),
			default => $this->_cacheFileAbsolutePath,
		};

		return $this->_cacheFileAbsolutePath;
	}

	public function getCacheFileAbsolutePath(): string
	{
		if ($this->alreadyCached === false) {
			$this->writeCachedVersion();
		}

		return $this->_cacheFileAbsolutePath;
	}

	/**
	 * Visszaadja a cache fájl relatív elérési útvonalát (megjelenítéshez).
	 */
	public function getCacheFileRelativePath(): false|string
	{
		if ($this->error) {
			return false;
		}

		if ($this->alreadyCached === false) {
			$this->writeCachedVersion();
		}

		return $this->_cacheFileRelativePath;
	}

	/**
	 * A PHP manual kommentjeiből összetákolt metódus mindenféle BMP fájlok
	 * megnyitására.
	 */
	public static function ImageCreateFromBMP(string $bmp_filename): ?GdImage
	{
		if (!$f1 = fopen($bmp_filename, "rb")) {
			return null;
		}

		$file = unpack("vfile_type/Vfile_size/Vreserved/Vbitmap_offset", fread($f1, 14));

		if ($file['file_type'] != 19778) {
			return null;
		}

		$bmp = unpack('Vheader_size/Vwidth/Vheight/vplanes/vbits_per_pixel' . '/Vcompression/Vsize_bitmap/Vhoriz_resolution' . '/Vvert_resolution/Vcolors_used/Vcolors_important', fread($f1, 40));

		if ($bmp === false) {
			return null;
		}

		$bmp['colors'] = 2 ** $bmp['bits_per_pixel'];

		if ($bmp['size_bitmap'] == 0) {
			$bmp['size_bitmap'] = $file['file_size'] - $file['bitmap_offset'];
		}

		$bmp['bytes_per_pixel'] = $bmp['bits_per_pixel'] / 8;
		$bmp['decal'] = ($bmp['width'] * $bmp['bytes_per_pixel'] / 4);
		$bmp['decal'] -= floor($bmp['width'] * $bmp['bytes_per_pixel'] / 4);
		$bmp['decal'] = 4 - (4 * $bmp['decal']);

		if ($bmp['decal'] == 4) {
			$bmp['decal'] = 0;
		}

		$palette = [];

		if ($bmp['colors'] < 16777216) {
			$palette = unpack('V' . $bmp['colors'], fread($f1, $bmp['colors'] * 4));
		}

		$img = fread($f1, $bmp['size_bitmap']);
		$vide = chr(0);

		$bmp_image_resource = imagecreatetruecolor($bmp['width'], $bmp['height']);

		if ($bmp_image_resource === false) {
			return null;
		}

		$p = 0;
		$y = $bmp['height'] - 1;

		while ($y >= 0) {
			$x = 0;

			while ($x < $bmp['width']) {
				if ($bmp['bits_per_pixel'] == 24) {
					$color = unpack("V", mb_substr($img, $p, 3) . $vide);
				} elseif ($bmp['bits_per_pixel'] == 16) {
					$color = unpack("v", mb_substr($img, $p, 2));

					if ($color) {
						$blue = (($color[1] & 0x001f) << 3) + 7;
						$green = (($color[1] & 0x03e0) >> 2) + 7;
						$red = (($color[1] & 0xfc00) >> 7) + 7;
						$color[1] = $red * 65536 + $green * 256 + $blue;
					}
				} elseif ($bmp['bits_per_pixel'] == 8) {
					$color = unpack("n", $vide . mb_substr($img, $p, 1));

					if ($color) {
						$color[1] = $palette[$color[1] + 1];
					}
				} elseif ($bmp['bits_per_pixel'] == 4) {
					$color = unpack("n", $vide . mb_substr($img, intval(floor($p)), 1));

					if ($color) {
						if (($p * 2) % 2 == 0) {
							$color[1] = ($color[1] >> 4);
						} else {
							$color[1] = ($color[1] & 0x0F);
						}

						$color[1] = $palette[$color[1] + 1];
					}
				} elseif ($bmp['bits_per_pixel'] == 1) {
					$color = unpack("n", $vide . mb_substr($img, intval(floor($p)), 1));

					if ($color) {
						if (($p * 8) % 8 == 0) {
							$color[1] = $color[1] >> 7;
						} elseif (($p * 8) % 8 == 1) {
							$color[1] = ($color[1] & 0x40) >> 6;
						} elseif (($p * 8) % 8 == 2) {
							$color[1] = ($color[1] & 0x20) >> 5;
						} elseif (($p * 8) % 8 == 3) {
							$color[1] = ($color[1] & 0x10) >> 4;
						} elseif (($p * 8) % 8 == 4) {
							$color[1] = ($color[1] & 0x8) >> 3;
						} elseif (($p * 8) % 8 == 5) {
							$color[1] = ($color[1] & 0x4) >> 2;
						} elseif (($p * 8) % 8 == 6) {
							$color[1] = ($color[1] & 0x2) >> 1;
						} elseif (($p * 8) % 8 == 7) {
							$color[1] = ($color[1] & 0x1);
						}

						$color[1] = $palette[$color[1] + 1];
					}
				} else {
					return null;
				}

				imagesetpixel($bmp_image_resource, $x, $y, $color[1]);

				++$x;
				$p += $bmp['bytes_per_pixel'];
			}
			--$y;
			$p += $bmp['decal'];
		}

		fclose($f1);

		return $bmp_image_resource;
	}

	public function imgHtmlTag($rollover = '', $alt = '', $border = 0, $script = '', $class = ''): string
	{
		if ($this->error !== '') {
			return '';
		}

		if ($this->alreadyCached === false) {
			$this->writeCachedVersion();
		}

		/*
		if ($rollover == '_on') {
			ResourceHandler::getInstance()->registerPreloadingImage($this->_cacheFileRelativePath);
		}
		*/

		if ($border !== '') {
			$border = " border=\"$border\"";
		}

		if ($class == '') {
			if ($rollover !== '' && $rollover == '_on' || $rollover == '_off') {
				$class = 'class="rollover" ';
			} else {
				$class = '';
			}
		} else {
			if ($rollover !== '' && $rollover == '_on' || $rollover == '_off') {
				$class = 'class="rollover ' . $class . '" ';
			} else {
				$class = 'class="' . $class . '" ';
			}
		}

		if (is_null($this->imageInfo)) {
			$this->imageInfo = getimagesize($this->_cacheFileRelativePath);
		}

		$info = $this->imageInfo;
		$attr = $info[3];

		//list($width, $height, $type, $attr) = $this->imageInfo;

		return "<img {$class}{$script} src=\"{$this->_cacheFileRelativePath}\" {$attr} alt=\"{$alt}\"{$border}>";
	}

	public function recordSize(): void
	{
		$info = $this->imageInfo;
		//list($width, $height, $type, $attr) = $this->imageInfo;

		$width = $info[0];
		$height = $info[1];

		if ($width > self::$max_size['width']) {
			self::$max_size['width'] = $width;
		}

		if ($height > self::$max_size['height']) {
			self::$max_size['height'] = $height;
		}
	}

	public static function resetMaxSize(): void
	{
		self::$max_size = [
			'width' => 0,
			'height' => 0,
		];
	}

	public static function getMaxSize(): array
	{
		$max_size = self::$max_size;
		self::resetMaxSize();

		return $max_size;
	}

	public static function ksortRecursive(&$array): void
	{
		ksort($array);

		foreach ($array as $k => $v) {
			if (is_array($v)) {
				self::ksortRecursive($array[$k]);
			}
		}
	}

	public function isZoomable(int $min_percent = 10): bool
	{
		if (is_null($this->_original_imageInfo)) {
			$this->_original_imageInfo = @getimagesize($this->inputFile);
		}

		if ($this->_original_imageInfo === false) {
			return false;
		}

		$originalWidth = $this->_original_imageInfo[0];
		$originalHeight = $this->_original_imageInfo[1];

		$this->finalWidth ??= 1;
		$this->finalHeight ??= 1;

		$width_percent = ($originalWidth / $this->finalWidth - 1) * 100;
		$height_percent = ($originalHeight / $this->finalHeight - 1) * 100;

		if ($width_percent > $min_percent || $height_percent > $min_percent) {
			return true;
		} else {
			return false;
		}
	}

	public static function makePath($path): bool
	{
		return @mkdir($path, 0o777, true);
	}
}
