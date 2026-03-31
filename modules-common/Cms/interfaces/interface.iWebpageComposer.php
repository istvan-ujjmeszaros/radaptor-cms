<?php

/**
 * Build-time webpage context interface.
 *
 * Provides access to page metadata and tree-building state during the build phase.
 * Asset registration and HTML emission live on iHtmlAssetRegistry / iHtmlTemplateRuntime.
 *
 * Note: renderKeywords(), renderPreloadingImages(), renderRobots(), and header() are
 * HTTP/response concerns and will be moved off this interface in a future cleanup.
 */
interface iWebpageComposer extends iTreeBuildContext
{
	public function getPageId(): ?int;

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getPagedata($key);

	public function getTheme(): ?AbstractThemeData;
	public function isEditable(): bool;
	public function addToTitle(string $addition): void;
	public function overrideLayoutType(string $layoutTypeName): void;
	public function getLayoutTypeName(): ?string;

	/**
	 * @return list<iLayoutComponent>
	 */
	public function getRenderedLayoutComponents(): array;
	public function registerRenderedLayoutComponent(iLayoutComponent $layoutComponent): void;

	// HTTP/response concerns — to be moved off in a future cleanup
	public function renderKeywords(): void;
	public function renderPreloadingImages(): void;
	public function renderRobots(): void;
	public static function header(string $header): void;
}
