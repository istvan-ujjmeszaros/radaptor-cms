<?php

interface iHtmlAssetRegistry
{
	public function registerLibrary(string $resource_name, bool $force_top = false): void;
	public function registerCss(string $resource_name): void;
	public function registerJs(string $resource_name, bool $top = false): void;
	public function registerModule(string $resource_name): void;
	public function registerInnerHtml(string $inner_html): void;
	public function registerClosingHtml(string $closing_html): void;

	/**
	 * @param string|array<string> $keys
	 */
	public function registerI18n(string|array $keys): void;

	public function getCss(): string;
	public function getJs(): string;
	public function getJsTop(): string;
	public function getJsBottom(): string;
	public function getLibraryDebugInfo(): string;
	public function fetchInnerHtml(): string;
	public function fetchClosingHtml(): string;
}
