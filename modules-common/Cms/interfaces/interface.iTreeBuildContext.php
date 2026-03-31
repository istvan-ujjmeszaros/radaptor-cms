<?php

interface iTreeBuildContext
{
	public function getPageId(): ?int;

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getPagedata($key);

	public function registerRenderedLayoutComponent(iLayoutComponent $layoutComponent): void;
	public function getLayoutTypeName(): ?string;
	public function addToTitle(string $addition): void;
	public function isEditable(): bool;
	public function getTheme(): ?AbstractThemeData;
	public function overrideLayoutType(string $layoutTypeName): void;
}
