<?php

declare(strict_types=1);

final class FormCapturePreviewTreeContext implements iTreeBuildContext
{
	public function __construct(private readonly ?AbstractThemeData $_theme = null)
	{
	}

	public function getPageId(): ?int
	{
		return null;
	}

	public function getPagedata($key)
	{
		return null;
	}

	public function registerRenderedLayoutComponent(iLayoutComponent $layoutComponent): void
	{
	}

	public function getLayoutTypeName(): ?string
	{
		return 'admin_default';
	}

	public function addToTitle(string $addition): void
	{
	}

	public function isEditable(): bool
	{
		return false;
	}

	public function getTheme(): ?AbstractThemeData
	{
		return $this->_theme;
	}

	public function overrideLayoutType(string $layoutTypeName): void
	{
	}
}
