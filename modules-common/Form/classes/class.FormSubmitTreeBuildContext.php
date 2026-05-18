<?php

declare(strict_types=1);

final class FormSubmitTreeBuildContext implements iTreeBuildContext
{
	/**
	 * @param array<string, mixed> $pagedata
	 */
	public function __construct(
		private readonly ?int $pageId,
		private readonly ?string $layoutTypeName = null,
		private readonly array $pagedata = [],
		private readonly ?AbstractThemeData $theme = null,
	) {
	}

	public static function fromSubmitContext(FormSubmitContext $context): self
	{
		$pagedata = [];
		$layout_type_name = null;
		$theme = null;

		if ($context->hostPageId !== null) {
			$data = ResourceTreeHandler::getResourceTreeEntryDataById($context->hostPageId);
			$pagedata = is_array($data) ? $data : [];

			if ($pagedata !== []) {
				$resource = new ResourceTypeWebpage($context->hostPageId, $pagedata);
				$layout_type_name = $resource->getView()->getLayoutTypeName();
				$theme = $resource->getView()->getTheme();
			}
		}

		return new self(
			pageId: $context->hostPageId,
			layoutTypeName: $layout_type_name,
			pagedata: $pagedata,
			theme: $theme,
		);
	}

	public function getPageId(): ?int
	{
		return $this->pageId;
	}

	public function getPagedata($key)
	{
		return $this->pagedata[$key] ?? null;
	}

	public function registerRenderedLayoutComponent(iLayoutComponent $layoutComponent): void
	{
	}

	public function getLayoutTypeName(): ?string
	{
		return $this->layoutTypeName;
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
		return $this->theme;
	}

	public function overrideLayoutType(string $layoutTypeName): void
	{
	}
}
