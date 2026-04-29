<?php

class ImagePredefinedAdminThumbnail extends PredefinedImageHandler
{
	public function getPathForManipulatedImage(): string
	{
		return $this->_originalPath
			|> ImageManipulator::fit(maxWidth: 180, maxHeight: 120, outputFormat: $this->_extension, enableZooming: false)
			|> ImageManipulator::cache(cacheSubdirectoryName: 'admin_thumbnail');
	}
}
