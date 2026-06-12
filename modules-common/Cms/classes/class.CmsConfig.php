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

	// Editing-session token: minted per editor open, scopes undo/redo history to the
	// browser tab. Rides the editor iframe URL and propagates onto mutation requests
	// the same way as the iframe marker.
	public const string EDITOR_SESSION_PARAM = 'radaptor_editor_session';

	// Legacy page-editor marker, kept as a page-scope alias during the migration.
	public const string PAGE_EDITOR_IFRAME_PARAM = 'radaptor_page_editor_iframe';
	public const string PAGE_EDITOR_IFRAME_VALUE = '1';

	public static function isEditorIframeRequest(): bool
	{
		if (self::requestParam(self::EDITOR_IFRAME_PARAM) === self::EDITOR_IFRAME_VALUE) {
			return true;
		}

		return self::requestParam(self::PAGE_EDITOR_IFRAME_PARAM) === self::PAGE_EDITOR_IFRAME_VALUE;
	}

	/**
	 * Active editor scope, or '' outside the editor iframe (global edit mode).
	 */
	public static function editorScope(): string
	{
		if (!self::isEditorIframeRequest()) {
			return '';
		}

		$scope = self::requestParam(self::EDITOR_SCOPE_PARAM);

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

	/**
	 * The editing-session token of the current request, or '' outside an editor session
	 * (global edit mode edits are not undoable).
	 */
	public static function editorSessionToken(): string
	{
		$token = self::requestParam(self::EDITOR_SESSION_PARAM);

		return preg_match('/^[a-f0-9]{16,64}$/', $token) === 1 ? $token : '';
	}

	/**
	 * Editor iframes propagate the marker on every htmx mutation request (POST body),
	 * so mutation-rendered fragments keep the iframe markup variant. Query string wins
	 * over the body copy.
	 */
	private static function requestParam(string $name): string
	{
		$value = (string)Request::_GET($name, '');

		if ($value !== '') {
			return $value;
		}

		return (string)Request::_POST($name, '');
	}
}
