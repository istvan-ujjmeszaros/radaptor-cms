<?php

abstract class PredefinedImageHandler implements iPredefinedImage
{
	protected string $_extension;

	public function __construct(
		protected string $_originalPath,
		protected string $_savename
	) {
		$pathinfo = pathinfo($this->_savename);
		$this->_extension = $pathinfo['extension'];
	}

	public static function factory(string $predefinedImageHandlerName, string $originalPath, string $savename): ?iPredefinedImage
	{
		if ($predefinedImageHandlerName == '') {
			return null;
		}

		$predefinedImageHandlerName = str_replace(' ', '', ucwords(mb_strtolower(str_replace('_', ' ', $predefinedImageHandlerName))));

		$PredefindImageHandlerClassName = 'ImagePredefined' . $predefinedImageHandlerName;

		if (!AutoloaderFromGeneratedMap::autoloaderClassExists($PredefindImageHandlerClassName)) {
			return null;
		}

		$return = new $PredefindImageHandlerClassName($originalPath, $savename);

		if ($return instanceof iPredefinedImage) {
			return $return;
		} else {
			return null;
		}
	}

	/**
	 * Rewrites the filename with a predefined name, preserving the file extension.
	 *
	 * @param string $filename The original filename.
	 * @param string $predefinedName The new predefined name for the file.
	 * @return string The rewritten filename.
	 */
	public static function rewriteFileName(string $filename, string $predefinedName): string
	{
		$pathInfo = pathinfo($filename);
		$dirname = (string) ($pathInfo['dirname'] ?? '');
		$filename = (string) ($pathInfo['filename'] ?? $filename);
		$extension = (string) ($pathInfo['extension'] ?? '');
		$rewritten = $extension !== ''
			? $filename . '.' . $predefinedName . '.' . $extension
			: $filename . '.' . $predefinedName;

		if ($dirname === '' || $dirname === '.') {
			return $rewritten;
		}

		return $dirname . DIRECTORY_SEPARATOR . $rewritten;
	}

	public static function getSeoUrl(int $resource_id, string $predefinedName): string
	{
		$url = Url::getSeoUrl($resource_id, false);

		$exploded_url = explode('/', (string) $url);

		$filename = $exploded_url[count($exploded_url) - 1];

		$filename = self::rewriteFileName($filename, $predefinedName);

		$exploded_url[count($exploded_url) - 1] = $filename;

		return implode('/', $exploded_url);
	}

	public static function getImageData(int $resource_id, int $file_id, string $predefinedName): array
	{
		$src = self::getSeoUrl($resource_id, $predefinedName);

		$image = PredefinedImageHandler::factory($predefinedName, FileContainer::realPathFromFileId($file_id), basename($src));

		$image_data = getimagesize($image->getPathForManipulatedImage());

		return [
			'src' => $src,
			'width' => $image_data[0],
			'height' => $image_data[1],
			'imgsizeinfo' => $image_data[3],
		];
	}
}
