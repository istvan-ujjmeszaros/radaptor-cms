<?php

interface iHtmlTemplateRuntime extends iHtmlAssetRegistry
{
	public function getTheme(): ?AbstractThemeData;
	public function getLangId(): string;
	public function getPageId(): ?int;
	public function getTitle(): string;
	public function getDescription(): string;

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getPagedata(string $key);
	public function isEditable(): bool;
	public function recordTemplateDebug(string $templateName, string $templatePath, float $durationMs): void;
}
