<?php

class ImagePredefinedAdminThumbnail extends PredefinedImageHandler
{
	public function getPathForManipulatedImage(): string
	{
		$parameters = [
			'outputFormat' => $this->_extension,
			'sizingMethod' => 'fit',
			'enableZooming' => false,
			'maxWidth' => 180,
			'maxHeight' => 120,
		];

		$image = new ImageManipulator($this->_originalPath, $parameters, 'admin_thumbnail');

		return $image->getImageCacheHandler()->getCacheFileAbsolutePath();
	}
}
