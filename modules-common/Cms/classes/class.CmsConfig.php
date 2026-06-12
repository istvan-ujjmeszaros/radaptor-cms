<?php

class CmsConfig
{
	public const string EDITMODE = 'cms.editmode';
	public const bool EDITMODE_DEFAULTVALUE = true;

	// Unified editor iframe marker. The editor opens pages in an iframe and the scope
	// decides which inserter kind renders inside it: page scope shows widget inserters
	// only, form scope shows form-field inserters only. Without the marker (global edit
	// mode while browsing) both inserter kinds render.
	public const string EDITOR_IFRAME_PARAM = 'radaptor_editor_iframe';
	public const string EDITOR_IFRAME_VALUE = '1';
	public const string EDITOR_SCOPE_PARAM = 'radaptor_editor_scope';
	public const string EDITOR_SCOPE_PAGE = 'page';
	public const string EDITOR_SCOPE_FORM = 'form';

	// Legacy page-editor marker, kept as a page-scope alias during the migration.
	public const string PAGE_EDITOR_IFRAME_PARAM = 'radaptor_page_editor_iframe';
	public const string PAGE_EDITOR_IFRAME_VALUE = '1';

	public static function isEditorIframeRequest(): bool
	{
		if ((string)Request::_GET(self::EDITOR_IFRAME_PARAM, '') === self::EDITOR_IFRAME_VALUE) {
			return true;
		}

		return (string)Request::_GET(self::PAGE_EDITOR_IFRAME_PARAM, '') === self::PAGE_EDITOR_IFRAME_VALUE;
	}

	/**
	 * Active editor scope, or '' outside the editor iframe (global edit mode).
	 */
	public static function editorScope(): string
	{
		if (!self::isEditorIframeRequest()) {
			return '';
		}

		$scope = (string)Request::_GET(self::EDITOR_SCOPE_PARAM, '');

		if (in_array($scope, [self::EDITOR_SCOPE_PAGE, self::EDITOR_SCOPE_FORM], true)) {
			return $scope;
		}

		// The legacy marker and a bare editor marker both mean the page editor.
		return self::EDITOR_SCOPE_PAGE;
	}

	public static function isPageEditorIframeRequest(): bool
	{
		return self::isEditorIframeRequest();
	}
}
