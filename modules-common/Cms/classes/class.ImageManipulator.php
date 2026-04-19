<?php

/**
 * Resizes, watermarks, and masks images based on the provided parameters.
 *
 * Upon instantiation, the class checks if the desired modified version of the image already exists.
 * If saving to a file, it verifies the existence and integrity of the requested file.
 * If outputting to the browser, it checks for a cached version and its integrity.
 *
 * If necessary, the class performs the requested modifications and either saves the modified version
 * to the desired location or saves it to the cache directory and displays the cached version.
 *
 * @author István Ujj-Mészáros, <info@styu.hu>
 */
class ImageManipulator
{
	public const string ERROR_NO_ERROR = '';
	public const string ERROR_OUT_OF_MEMORY = 'Possible misconfiguration of settings or insufficient memory for resizing the image.';
	public const string ERROR_VALID_PNG_REQUIRED_FOR_CUTTER = 'The cutter file must be a valid PNG image.';
	public const string ERROR_OPENING_CUTTER_FILE = 'Unable to open the cutter file.';
	public const string ERROR_CUTTER_SIZE_NOT_MATCH = 'Cutter image size must match the final size of the resized image.';
	public const string ERROR_VALID_PNG_REQUIRED_FOR_MASK = 'The mask file must be a valid PNG image.';
	public const string ERROR_OPENING_MASK_FILE = 'Unable to open the mask file.';
	public const string ERROR_MASK_SIZE_NOT_MATCH = 'Mask image size must match the final size of the resized image.';
	public const string ERROR_WHILE_OPENING_CUTTER_IMAGE = 'Error occurred while opening the cutter image.';
	public const string ERROR_WHILE_OPENING_MASK_IMAGE = 'Error occurred while opening the mask image.';

	/**
	 * Contains the error message in case of an error (contains only one message).
	 *
	 * @var string Error message
	 */
	public string $error = self::ERROR_NO_ERROR;

	public ?GdImage $image_resource = null;
	protected bool $_isReady = false;

	/**
	 * The path to the directory containing image masks (constant).
	 *
	 * @access protected
	 * @var string
	 */
	protected string $_configMaskDir = '';

	/**
	 * The path to the directory containing image watermarks (constant).
	 *
	 * @access protected
	 * @var string
	 */
	protected string $_configWatermarkDir = '';

	/**
	 * The horizontal (X) offset of the image within the frame (in pixels).
	 * This value is automatically calculated based on the 'boxAlign' property.
	 *
	 * @access protected
	 * @var int
	 */
	protected int $_boxOffsetX = 0;

	/**
	 * The vertical (Y) offset of the image within the frame (in pixels).
	 * This value is automatically calculated based on the 'boxAlign' property.
	 *
	 * @access protected
	 * @var int
	 */
	protected int $_boxOffsetY = 0;

	/**
	 * The width that extends beyond the maximum size (for cropping).
	 *
	 * @access protected
	 * @var int
	 */
	protected int $_overlappedWidth = -1;

	/**
	 * The height that extends beyond the maximum size (for cropping).
	 *
	 * @access protected
	 * @var int
	 */
	protected int $_overlappedHeight = -1;

	/**
	 * The final width (in pixels).
	 *
	 * @access protected
	 * @var int
	 */
	protected int $_finalWidth = -1;

	/**
	 * The final height (in pixels).
	 *
	 * @access protected
	 * @var int
	 */
	protected int $_finalHeight = -1;
	protected array $_parameters = [];
	protected ImageHandler $imageCacheHandler;

	public function getImageCacheHandler(): ImageHandler
	{
		return $this->imageCacheHandler;
	}

	private ?GdImage $originalImageResource = null;

	private int $originalWidth;

	private int $originalHeight;

	private function _initParameters(array $new_parameters): void
	{
		// Default values
		$this->_parameters['watermarks'] = [];  // Array containing watermarks to be applied to the new image.
		$this->_parameters['cutter'] = '';           // GIF file containing the cutting pattern for the new image.
		$this->_parameters['mask'] = '';             // Transparent PNG (png24) mask to be applied to the new image.
		$this->_parameters['boxHeight'] = -1;        // Height of the box containing the new image (in pixels).
		$this->_parameters['boxWidth'] = -1;         // Width of the box containing the new image (in pixels).
		$this->_parameters['maxHeight'] = -1;        // Maximum height of the new image (in pixels).
		$this->_parameters['maxWidth'] = -1;         // Maximum width of the new image (in pixels).
		$this->_parameters['bgOpacity'] = 127;       // Opacity of the background color of the frame containing the image (0-127).
		$this->_parameters['bgColor'] = '';          // Background color of the frame containing the image.
		$this->_parameters['boxAlign'] = 'C';        // Alignment of the image within the frame.
		$this->_parameters['boxed'] = '';            // If not null, the new image will be placed in a frame of a given size.
		$this->_parameters['enableZooming'] = false; // If true, zooming in on the image is allowed, otherwise only shrinking is allowed.
		$this->_parameters['sizingMethod'] = '';     // The method of resizing the image (fit/crop/stretch/nochange).
		$this->_parameters['quality'] = 100;         // Quality of the output image (jpg) (1->100).
		$this->_parameters['outputFormat'] = '';     // Format of the output image (jpg, png, gif).

		// Set specific values
		ImageHandler::setParameters($new_parameters, $this->_parameters);
	}

	/**
	 * Opens the given image, performs the requested operations based on predefinedSettings,
	 * then saves the resulting image to the cache and sets the attribute pointing to the cached version
	 * (or if the cached version already existed, sets the attribute based on that).
	 *
	 * @access public
	 */
	public function __construct(string $inputFile, array $parameters, string $cacheSubdirectoryName, bool $cachePathUseFilename = true)
	{
		$this->_initParameters($parameters);
		$this->imageCacheHandler = new ImageHandler($inputFile, $this->_parameters, $cacheSubdirectoryName, $cachePathUseFilename);

		if ($this->imageCacheHandler->error !== '') {
			return;
		}

		if ($this->imageCacheHandler->getError() !== '') {
			Kernel::abort($this->imageCacheHandler->getError());
		}

		if (!$this->imageCacheHandler->alreadyCached) {
			$this->originalImageResource = $this->imageCacheHandler->openImageFile();

			if (!is_null($this->originalImageResource)) {
				$this->originalWidth = imagesx($this->originalImageResource);
				$this->originalHeight = imagesy($this->originalImageResource);
				$this->_doAllRequiredModifications();
			}
		}
	}

	/**
	 * Calculates the new dimensions of the image based on the requested modifications.
	 *
	 * @return void True if successful, false otherwise
	 */
	protected function _calculateNewDimensions(): void
	{
		if ($this->error) {
			return;
		}

		$originalWidth = $this->originalWidth;
		$originalHeight = $this->originalHeight;
		$maxWidth = $this->_parameters['maxWidth'];
		$maxHeight = $this->_parameters['maxHeight'];
		$enableZooming = $this->_parameters['enableZooming'];

		switch ($this->_parameters['sizingMethod']) {
			case 'stretch':
				$this->_overlappedWidth = $maxWidth;
				$this->_overlappedHeight = $maxHeight;
				$this->_finalWidth = $maxWidth;
				$this->_finalHeight = $maxHeight;

				break;

			case 'fit':
				$this->_calculateFitDimensions($originalWidth, $originalHeight, $maxWidth, $maxHeight, $enableZooming);

				break;

			case 'crop':
				$this->_calculateCropDimensions($originalWidth, $originalHeight, $maxWidth, $maxHeight, $enableZooming);

				break;

			default:
				$this->error = "Invalid sizing method: {$this->_parameters['sizingMethod']}";

				return;
		}

		// Ensure dimensions are integers
		$this->_overlappedWidth = (int)$this->_overlappedWidth;
		$this->_overlappedHeight = (int)$this->_overlappedHeight;
		$this->_finalWidth = (int)$this->_finalWidth;
		$this->_finalHeight = (int)$this->_finalHeight;
	}

	/**
	 * Calculates new dimensions for the 'fit' resizing method.
	 *
	 * @param int $originalWidth
	 * @param int $originalHeight
	 * @param int $maxWidth
	 * @param int $maxHeight
	 * @param bool $enableZooming
	 * @return void
	 */
	protected function _calculateFitDimensions(int $originalWidth, int $originalHeight, int $maxWidth, int $maxHeight, bool $enableZooming): void
	{
		// Check if image is smaller than max dimensions and zooming is allowed
		if ($originalWidth < $maxWidth && $originalHeight < $maxHeight && !$enableZooming) {
			// No changes needed
			$this->_overlappedWidth = $originalWidth;
			$this->_overlappedHeight = $originalHeight;
			$this->_finalWidth = $originalWidth;
			$this->_finalHeight = $originalHeight;
		} else {
			// Calculate aspect ratios
			$originalAspectRatio = $originalWidth / $originalHeight;
			$maxAspectRatio = $maxWidth / $maxHeight;

			if ($originalAspectRatio < $maxAspectRatio) {
				// Fit to width
				$this->_overlappedWidth = $maxWidth;
				$this->_overlappedHeight = intval(round($originalHeight / $originalAspectRatio));
			} else {
				// Fit to height
				$this->_overlappedWidth = intval(round($originalWidth * $originalAspectRatio));
				$this->_overlappedHeight = $maxHeight;
			}

			$this->_finalWidth = $this->_overlappedWidth;
			$this->_finalHeight = $this->_overlappedHeight;
		}
	}

	/**
	 * Calculates new dimensions for the 'crop' resizing method.
	 *
	 * @param int $originalWidth
	 * @param int $originalHeight
	 * @param int $maxWidth
	 * @param int $maxHeight
	 * @param bool $enableZooming
	 * @return void
	 */
	protected function _calculateCropDimensions(int $originalWidth, int $originalHeight, int $maxWidth, int $maxHeight, bool $enableZooming): void
	{
		// Check if image is smaller than max dimensions and zooming is not allowed
		if ($originalWidth < $maxWidth && $originalHeight < $maxHeight && !$enableZooming) {
			// No changes needed
			$this->_overlappedWidth = $originalWidth;
			$this->_overlappedHeight = $originalHeight;
			$this->_finalWidth = $originalWidth;
			$this->_finalHeight = $originalHeight;
		} else {
			// Calculate aspect ratios
			$originalAspectRatio = $originalWidth / $originalHeight;
			$maxAspectRatio = $maxWidth / $maxHeight;

			if ($originalAspectRatio < $maxAspectRatio) {
				// Fit to height, crop width
				$this->_overlappedWidth = intval(round($originalWidth * ($maxHeight / $originalHeight)));
				$this->_overlappedHeight = $maxHeight;
			} elseif ($originalAspectRatio > $maxAspectRatio) {
				// Fit to width, crop height
				$this->_overlappedWidth = $maxWidth;
				$this->_overlappedHeight = intval(round($originalHeight * ($maxWidth / $originalWidth)));
			} else {
				// No cropping needed, just resize
				$this->_overlappedWidth = $maxWidth;
				$this->_overlappedHeight = $maxHeight;
			}
			$this->_finalWidth = $maxWidth;
			$this->_finalHeight = $maxHeight;
		}

		// Calculate offsets for cropping (center the image)
		$this->_boxOffsetX = ($this->_overlappedWidth - $maxWidth) / 2;
		$this->_boxOffsetY = ($this->_overlappedHeight - $maxHeight) / 2;
	}

	/**
	 * Performs the image resizing to the calculated dimensions.
	 *
	 * @return bool True if resizing is successful, false otherwise.
	 */
	protected function _doResizing(): bool
	{
		if ($this->error) {
			return false; // Return if a previous error occurred
		}

		// Create a temporary image with the overlapped dimensions
		$tmpImage = imagecreatetruecolor($this->_overlappedWidth, $this->_overlappedHeight);

		if ($tmpImage === false) {
			$this->originalImageResource = null; // Release original image reference
			$this->error = self::ERROR_OUT_OF_MEMORY . " (line " . __LINE__ . ")";

			return false;
		}

		// Enable alpha blending and set background color for the temporary image
		ImageHandler::setAlphaBlending($tmpImage);
		ImageHandler::setProperBgColor($tmpImage, $this->_parameters);

		// Resample the original image into the temporary image
		if (!imagecopyresampled(
			$tmpImage,
			$this->originalImageResource,
			0,
			0,
			0,
			0,
			$this->_overlappedWidth,
			$this->_overlappedHeight,
			$this->originalWidth,
			$this->originalHeight
		)) {
			$this->originalImageResource = null;
			$tmpImage = null;
			$this->error = self::ERROR_OUT_OF_MEMORY . " (line " . __LINE__ . ")";

			return false;
		}

		// Clean up the original image resource
		$this->originalImageResource = null;

		// Create the final image resource based on the sizing method
		switch ($this->_parameters['sizingMethod']) {
			case 'fit':
			case 'crop':
			case 'stretch':
				$this->image_resource = imagecreatetruecolor($this->_finalWidth, $this->_finalHeight);

				break;
		}

		// Check if image resource creation was successful
		if (!$this->image_resource) {
			$tmpImage = null;
			$this->error = self::ERROR_OUT_OF_MEMORY . " (line " . __LINE__ . ")";

			return false;
		}

		// Enable alpha blending and set background color for the final image
		ImageHandler::setAlphaBlending($this->image_resource);
		ImageHandler::setProperBgColor($this->image_resource, $this->_parameters);

		// Copy the temporary image to the final image based on the sizing method
		switch ($this->_parameters['sizingMethod']) {
			case 'fit':
			case 'stretch':
				if (!imagecopy($this->image_resource, $tmpImage, 0, 0, 0, 0, $this->_finalWidth, $this->_finalHeight)) {
					$this->image_resource = null;
					$tmpImage = null;
					$this->error = self::ERROR_OUT_OF_MEMORY . " (line " . __LINE__ . ")";

					return false;
				}

				break;

			case 'crop':
				$xOffset = (int)abs(round(($this->_overlappedWidth - $this->_finalWidth) / 2));
				$yOffset = (int)abs(round(($this->_overlappedHeight - $this->_finalHeight) / 2));

				if (!imagecopy($this->image_resource, $tmpImage, 0, 0, $xOffset, $yOffset, $this->_overlappedWidth, $this->_overlappedHeight)) {
					$this->image_resource = null;
					$tmpImage = null;
					$this->error = self::ERROR_OUT_OF_MEMORY . " (line " . __LINE__ . ")";

					return false;
				}

				break;
		}

		// Clean up the temporary image
		$tmpImage = null;

		return true;
	}

	/**
	 * Modifies the input image based on the set class attributes.
	 *
	 * @return bool True if resizing is successful, false otherwise.
	 */
	protected function _resizeImage(): bool
	{
		if ($this->error) {
			return false; // Return if a previous error occurred
		}

		// Check if resizing is necessary
		if ($this->originalWidth != $this->_parameters['maxWidth'] || $this->originalHeight != $this->_parameters['maxHeight']) {
			$this->_calculateNewDimensions(); // Calculate new dimensions

			if (!$this->_doResizing()) {
				return false; // Resizing failed
			}
		} else {
			// No resizing needed, set dimensions to match original
			$this->_overlappedWidth = $this->_parameters['maxWidth'];
			$this->_overlappedHeight = $this->_parameters['maxHeight'];
			$this->_finalWidth = $this->_parameters['maxWidth'];
			$this->_finalHeight = $this->_parameters['maxHeight'];
			$this->image_resource = $this->originalImageResource;
		}

		return true; // Resizing successful (or not needed)
	}

	/**
	 * Places the image into a box of a specified size, aligned to the given position.
	 *
	 * @return bool True if successful, false otherwise.
	 */
	protected function _dropImageIntoBox(): bool
	{
		if ($this->error) {
			return false; // Early return if a previous error occurred
		}

		// If boxing is not enabled, no need to proceed
		if (!$this->_parameters['boxed']) {
			return true;
		}

		// If the image is larger than the box, no boxing needed
		if ($this->_finalWidth > $this->_parameters['boxWidth'] && $this->_finalHeight > $this->_parameters['boxHeight']) {
			return true;
		}

		// Ensure the box is at least as large as the image
		$this->_parameters['boxWidth']  = max($this->_finalWidth, $this->_parameters['boxWidth']);
		$this->_parameters['boxHeight'] = max($this->_finalHeight, $this->_parameters['boxHeight']);

		// Calculate the offset based on alignment (T, B, C, L, R, TL, TR, BL, BR)
		[$this->_boxOffsetX, $this->_boxOffsetY] = $this->calculateBoxOffset(
			$this->_parameters['boxAlign'],
			$this->_parameters['boxWidth'],
			$this->_parameters['boxHeight'],
			$this->_finalWidth,
			$this->_finalHeight
		);

		// Create a temporary image with the box dimensions
		$tmpImage = imagecreatetruecolor($this->_parameters['boxWidth'], $this->_parameters['boxHeight']);

		if (!$tmpImage) {
			$this->handleError(self::ERROR_OUT_OF_MEMORY);

			return false;
		}

		ImageHandler::setAlphaBlending($tmpImage);
		ImageHandler::setProperBgColor($tmpImage, $this->_parameters);

		// Copy the resized image into the box at the calculated offset
		if (!imagecopy($tmpImage, $this->image_resource, $this->_boxOffsetX, $this->_boxOffsetY, 0, 0, $this->_finalWidth, $this->_finalHeight)) {
			$this->handleError(self::ERROR_OUT_OF_MEMORY);

			return false;
		}

		// Replace the image resource with the boxed image
		$this->image_resource = null;
		$this->image_resource = $tmpImage;

		// Update the final dimensions to match the box
		$this->_finalWidth = $this->_parameters['boxWidth'];
		$this->_finalHeight = $this->_parameters['boxHeight'];

		return true;
	}

	/**
	 * Calculates X and Y offsets for positioning the image within the box.
	 *
	 * @param string $align The alignment code (T, B, C, L, R, TL, TR, BL, BR)
	 * @param int $boxWidth
	 * @param int $boxHeight
	 * @param int $imageWidth
	 * @param int $imageHeight
	 * @return array [xOffset, yOffset]
	 */
	protected function calculateBoxOffset(string $align, int $boxWidth, int $boxHeight, int $imageWidth, int $imageHeight): array
	{
		$xOffset = 0;
		$yOffset = 0;

		switch ($align) {
			case 'T': // Top Center
				$xOffset = round(($boxWidth - $imageWidth) / 2);

				break;

			case 'B': // Bottom Center
				$xOffset = round(($boxWidth - $imageWidth) / 2);
				$yOffset = $boxHeight - $imageHeight;

				break;

			case 'C': // Center
				$xOffset = round(($boxWidth - $imageWidth) / 2);
				$yOffset = round(($boxHeight - $imageHeight) / 2);

				break;

			case 'L': // Left Center
				$yOffset = round(($boxHeight - $imageHeight) / 2);

				break;

			case 'R': // Right Center
				$xOffset = $boxWidth - $imageWidth;
				$yOffset = round(($boxHeight - $imageHeight) / 2);

				break;

			case 'TL': // Top Left
				break; // No offset needed

			case 'TR': // Top Right
				$xOffset = $boxWidth - $imageWidth;

				break;

			case 'BL': // Bottom Left
				$yOffset = $boxHeight - $imageHeight;

				break;

			case 'BR': // Bottom Right
				$xOffset = $boxWidth - $imageWidth;
				$yOffset = $boxHeight - $imageHeight;

				break;

			default: // Default to Center
				$xOffset = round(($boxWidth - $imageWidth) / 2);
				$yOffset = round(($boxHeight - $imageHeight) / 2);
		}

		return [$xOffset, $yOffset];
	}

	/**
	 * Handles errors that occur during image manipulation.
	 *
	 * Logs the error, sets the error message in the class property, and optionally destroys the image resource.
	 *
	 * @param string $errorMessage The error message
	 * @param bool $destroyImageResource Whether to destroy the image resource
	 */
	protected function handleError(string $errorMessage, bool $destroyImageResource = true): void
	{
		// Log the error (you can customize this based on your logging setup)
		error_log("ImageManipulator Error: $errorMessage on line " . __LINE__);

		// Set the error message in the class property
		$this->error = $errorMessage;

		// Optionally destroy the image resource to free up memory
		if ($destroyImageResource && $this->image_resource) {
			$this->image_resource = null; // Reset the resource
		}
	}

	/**
	 * Applies an image mask to the input image.
	 *
	 * @return bool True if the mask was applied successfully, false otherwise.
	 */
	protected function _addMask(): bool
	{
		if ($this->error) {
			return false; // Early return if a previous error occurred
		}

		// If no mask is specified, no need to proceed
		$maskPath = $this->_parameters['mask'];

		if (empty($maskPath)) {
			return true;
		}

		// Check if the mask file exists
		if (!file_exists($maskPath)) {
			$this->error = self::ERROR_OPENING_MASK_FILE;

			return false;
		}

		// Get mask image information
		$imageInfo = getimagesize($maskPath);
		$maskWidth = $imageInfo[0];
		$maskHeight = $imageInfo[1];

		// Only PNG masks are supported
		if ($imageInfo['mime'] !== 'image/png') {
			$this->error = self::ERROR_VALID_PNG_REQUIRED_FOR_MASK;

			return false;
		}

		// Check if mask dimensions match the final image dimensions
		if ($maskWidth != $this->_finalWidth || $maskHeight != $this->_finalHeight) {
			$this->error = self::ERROR_MASK_SIZE_NOT_MATCH;

			return false;
		}

		// Load the mask image
		$maskImage = @imagecreatefrompng($maskPath);

		if (!$maskImage) {
			$this->error = self::ERROR_WHILE_OPENING_MASK_IMAGE;

			return false;
		}

		// Ensure alpha blending is enabled for both images (since setAlphaBlending might disable it)
		imagealphablending($maskImage, true);
		imagealphablending($this->image_resource, true);

		// Apply the mask to the image
		imagecopy($this->image_resource, $maskImage, 0, 0, 0, 0, $maskWidth, $maskHeight);

		// Clean up the mask image resource
		$maskImage = null;

		return true; // Mask applied successfully
	}

	/**
	 * Adds a watermark to the image.
	 *
	 * @return bool True if the watermark was added successfully, false otherwise.
	 */
	protected function _addWatermark(): bool
	{
		if ($this->error) {
			return false; // Early return if a previous error occurred
		}

		$watermark_config = $this->_parameters['watermark'] ?? null;

		if (!is_array($watermark_config) || $watermark_config === []) {
			$watermarks = $this->_parameters['watermarks'] ?? [];
			$watermark_config = is_array($watermarks) ? reset($watermarks) : null;
		}

		if (!is_array($watermark_config) || $watermark_config === []) {
			return true;
		}

		$watermarkPath = (string) ($watermark_config['file'] ?? '');

		if ($watermarkPath === '' || !file_exists($watermarkPath)) {
			return true;
		}

		// Load the watermark image
		$watermark = @imagecreatefrompng($watermarkPath); // Or appropriate function based on file type

		if ($watermark === false) {
			return true;
		}

		// ... (Calculate watermark position based on $watermarkPosition and other parameters)

		// Apply the watermark to the image
		imagecopy($this->image_resource, $watermark, 0, 0, 0, 0, imagesx($watermark), imagesy($watermark));

		// Clean up the watermark image resource
		$watermark = null;

		return true; // Watermark added successfully (or no watermark to add)
	}

	/**
	 * Cuts the input image using a cutter file.
	 *
	 * This method applies a PNG cutter image to the input image. The cutter image
	 * should be the same size as the final dimensions of the resized image. The
	 * cutter image's alpha channel is used to determine the transparency of the
	 * resulting image.
	 *
	 * @return bool Returns true if successful, false otherwise.
	 */
	protected function _cutByFile(): bool
	{
		if ($this->error) {
			return false;
		}

		// If no cutter file is specified, no need to proceed.
		$cutterPath = $this->_parameters['cutter'];

		if (empty($cutterPath)) {
			return true;
		}

		// Check if the cutter file exists.
		if (!file_exists($cutterPath)) {
			$this->error = self::ERROR_OPENING_CUTTER_FILE;

			return false;
		}

		// Get information about the cutter image.
		$imageInfo = getimagesize($cutterPath);

		// Ensure the cutter image is a PNG.
		if ($imageInfo['mime'] !== 'image/png') {
			$this->error = self::ERROR_VALID_PNG_REQUIRED_FOR_CUTTER;

			return false;
		}

		// Load the cutter image.
		$cutterImage = @imagecreatefrompng($cutterPath);

		if (!$cutterImage) {
			$this->error = self::ERROR_WHILE_OPENING_CUTTER_IMAGE;

			return false;
		}

		// Get the dimensions of the cutter image.
		$width = $imageInfo[0];
		$height = $imageInfo[1];

		// Ensure the cutter image matches the final dimensions of the resized image.
		if ($width != $this->_finalWidth || $height != $this->_finalHeight) {
			$this->error = self::ERROR_CUTTER_SIZE_NOT_MATCH;
			$cutterImage = null; // Release cutter image reference

			return false;
		}

		// Enable alpha blending for the cutter image.
		ImageHandler::setAlphaBlending($cutterImage);

		// Create a temporary image.
		$tempImage = imagecreatetruecolor($width, $height);

		// Enable alpha blending and set the background color for the temporary image.
		ImageHandler::setAlphaBlending($tempImage);
		ImageHandler::setProperBgColor($tempImage, $this->_parameters);

		// Loop through the pixels of the images.
		for ($y = 0; $y < $height; $y++) {
			for ($x = 0; $x < $width; $x++) {
				// Get the color of the original image at the current pixel.
				$colorOriginal = imagecolorat($this->image_resource, $x, $y);

				// Extract the red, green, and blue components of the original color.
				$r = ($colorOriginal >> 16) & 0xFF;
				$g = ($colorOriginal >> 8) & 0xFF;
				$b = $colorOriginal & 0xFF;

				// Get the color of the cutter image at the current pixel.
				$colorCutter = imagecolorat($cutterImage, $x, $y);

				// Extract the alpha component from the cutter image and invert it.
				$alpha = 127 - (($colorCutter >> 24) & 0xFF);

				// Allocate a new color with the extracted RGB components and the inverted alpha.
				$colorNew = imagecolorallocatealpha($this->image_resource, $r, $g, $b, $alpha);

				// Set the pixel in the temporary image to the new color.
				imagesetpixel($tempImage, $x, $y, $colorNew);
			}
		}

		// Destroy the cutter image resource.
		$cutterImage = null;

		// Replace the original image resource with the temporary image.
		$this->image_resource = $tempImage;

		return true; // Cut successful
	}

	/**
	 * Performs all required modifications on the image.
	 *
	 * @return bool True if all modifications were successful, false otherwise.
	 */
	protected function _doAllRequiredModifications(): bool
	{
		if ($this->error) {
			return false; // Early return if a previous error occurred
		}

		// 2. Resizing
		if (!$this->_resizeImage()) {
			return false;
		}

		// 3. Placing into a box
		if (!$this->_dropImageIntoBox()) {
			return false;
		}

		// 4. Cutting based on the 'cutter' GIF file
		if (!$this->_cutByFile()) {
			return false;
		}

		// 5. Applying masks
		if (!$this->_addMask()) {
			return false;
		}

		// 6. Applying watermarks
		$this->_addWatermark();

		// 7. Saving (preparation)
		$this->_isReady = true;

		$this->imageCacheHandler->image_resource = $this->image_resource;
		$this->imageCacheHandler->imageInfo = [
			0 => $this->_finalWidth,
			1 => $this->_finalHeight,
			2 => false,
			3 => " height=\"$this->_finalHeight\" width=\"$this->_finalWidth\"",
		];

		return true; // All modifications successful
	}
}
