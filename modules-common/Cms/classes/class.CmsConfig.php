<?php

class CmsConfig
{
	public const string EDITMODE = 'cms.editmode';
	public const bool EDITMODE_DEFAULTVALUE = true;
	public const string PAGE_EDITOR_IFRAME_PARAM = 'radaptor_page_editor_iframe';
	public const string PAGE_EDITOR_IFRAME_VALUE = '1';

	public static function isPageEditorIframeRequest(): bool
	{
		return (string)Request::_GET(self::PAGE_EDITOR_IFRAME_PARAM, '') === self::PAGE_EDITOR_IFRAME_VALUE;
	}
}
