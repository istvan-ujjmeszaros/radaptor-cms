<?php

/**
 * Libraries for SoAdmin theme.
 *
 * Provides explicit library constants for the legacy admin theme.
 * Extends LibrariesCommon to inherit common bundles (jQuery, DataTables, etc.)
 *
 * Modernized: jQuery 3.7, DataTables 2.2, jsTree 3.3, Stimulus controllers (shared from new theme).
 */
class LibrariesSoAdmin extends LibrariesCommon
{
	/**
	 * jQuery 3.7.1 - modern version from CDN.
	 * The ^ prefix loads in <head> so inline scripts can use jQuery.
	 */
	public const string JQUERY = '
		js:^https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js,
	';

	/**
	 * Override QUERY to drop jQuery Migrate and BBQ (both incompatible with jQuery 3).
	 * BBQ used $.browser.msie (removed in jQuery 1.9+) and no code uses $.bbq.
	 */
	public const string QUERY = '
		JQUERY,
	';

	/**
	 * jQuery UI 1.14.1 - compatible with jQuery 3.7.x.
	 */
	public const string JQUERY_UI_10 = '
		JQUERY,
		css:https://cdn.jsdelivr.net/npm/jquery-ui@1.14.1/dist/themes/base/jquery-ui.min.css,
		js:https://cdn.jsdelivr.net/npm/jquery-ui@1.14.1/dist/jquery-ui.min.js,
	';

	/**
	 * DataTables 2.2 from CDN - default theme CSS (not Bootstrap 5).
	 */
	public const string DATATABLES = '
		JQUERY,
		css:https://cdn.datatables.net/2.2.0/css/dataTables.dataTables.min.css,
		js:https://cdn.datatables.net/2.2.0/js/dataTables.min.js,
	';

	/**
	 * DataTables with filter - DT2 has built-in filtering, no extra plugin.
	 */
	public const string DATATABLES_FILTER = '
		DATATABLES,
	';

	/**
	 * DataTables custom API - DT2 has native ajax.reload().
	 */
	public const string DATATABLES_CUSTOMAPI = '
		DATATABLES,
	';

	/**
	 * jsTree 3.3.17 from CDN + default theme CSS + Bootstrap Icons (used by jstree node type icons).
	 */
	public const string JSTREE = '
		JQUERY,
		css:https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.17/themes/default/style.min.css,
		js:https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.17/jstree.min.js,
		css:https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css,
	';

	/**
	 * Stimulus auto-loader (ES module) - SoAdmin-specific copy.
	 */
	public const string STIMULUS_LOADER = '
		module:/assets/themes/so-admin/js/stimulus-loader.js,
	';

	/**
	 * htmx 2.0.4 from CDN.
	 */
	public const string HTMX = '
		js:https://unpkg.com/htmx.org@2.0.4,
	';

	/**
	 * Override WIDGETTYPE_JSTREE - no longer loading old widgettype.jstree.js.
	 * Replaced by Stimulus controllers.
	 */
	public const string WIDGETTYPE_JSTREE = '';

	/**
	 * Override _ADMIN_DROPDOWN to remove anylinkmenu and missing admin_dropdown.css.
	 * The wrench dropdown now uses pure CSS (no jQuery plugins needed).
	 */
	public const string _ADMIN_DROPDOWN = '';

	/**
	 * Tippy.js 6 - modern tooltip library, replaces jQuery UI tooltip.
	 * Requires Popper.js (bundled in the iife build).
	 */
	public const string TIPPY = '
		js:https://unpkg.com/@popperjs/core@2/dist/umd/popper.min.js,
		js:https://unpkg.com/tippy.js@6/dist/tippy-bundle.umd.min.js,
		css:https://unpkg.com/tippy.js@6/dist/tippy.css,
	';

	/**
	 * Flatpickr 4.6.13 - modern date/datetime picker via Stimulus controller.
	 * Same CDN as RadaptorPortalAdmin theme.
	 */
	public const string FLATPICKR = '
		css:https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css,
		js:https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js,
		js:https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/hu.js,
	';

	/**
	 * Override old CALENDAR to use Flatpickr instead of legacy JSCalendar.
	 */
	public const string CALENDAR = '
		FLATPICKR,
	';

	/**
	 * Site CSS bundle for SoAdmin theme.
	 * Uses local theme-specific assets.
	 */
	public const string __ADMIN_SITE = '
		COMMON,
		JQUERY_UI_10,
		/assets/themes/so-admin/admin-site/css-reset/html5-boilerplate.css,
		/assets/themes/so-admin/admin-site/admin-site/admin-site.css,
		/assets/themes/so-admin/admin-site/buttons.css,
	';

	/**
	 * Edit mode CSS - widget editing, insert blocks, editBar toolbar.
	 * Uses local admin.css with glossy red styling.
	 */
	public const string __ADMIN_EDIT_MODE = '
		__COMMON_ADMIN,
	';

	/**
	 * Override __COMMON_ADMIN to use local admin.css with glossy red styling.
	 */
	public const string __COMMON_ADMIN = '
		/assets/themes/so-admin/css/admin.css,
	';

	/**
	 * User list widget - uses local widgettype.userList.js (rewritten for DT2).
	 */
	public const string __ADMIN_USERLIST = '
		DATATABLES_FILTER,
		/assets/themes/so-admin/admin-site/js/widgettype.userList.js,
	';

	/**
	 * Blog list widget - uses local widgettype.blogList.js (rewritten for DT2).
	 */
	public const string __ADMIN_BLOGLIST = '
		DATATABLES_FILTER,
		/assets/themes/so-admin/admin-site/js/widgettype.blogList.js,
	';

	/**
	 * Resource ACL selector - uses local widgettype.resourceAclSelector.js (rewritten for DT2).
	 */
	public const string __ADMIN_RESOURCE_ACL_SELECTOR = '
		DATATABLES_FILTER,
		/assets/themes/so-admin/admin-site/js/widgettype.resourceAclSelector.js,
	';

	/**
	 * Override WIDGET_EDIT to use local files with .widgetSelector selector.
	 */
	public const string WIDGET_EDIT = '
		JQUERY,
		JQUERY_UI_10,
		COMBOBOX,
		QTIP,
		/assets/themes/so-admin/js/widget-edit.js,
	';

	/**
	 * Resources tree widget - uses Stimulus controllers (shared from new theme).
	 */
	public const string __ADMIN_RESOURCES = '
		JSTREE,
		HTMX,
		STIMULUS_LOADER,
	';

	/**
	 * Roles tree widget - uses Stimulus controllers.
	 */
	public const string __ADMIN_ROLES = '
		JSTREE,
		HTMX,
		STIMULUS_LOADER,
	';

	/**
	 * Usergroups tree widget - uses Stimulus controllers.
	 */
	public const string __ADMIN_USERGROUPS = '
		JSTREE,
		HTMX,
		STIMULUS_LOADER,
	';

	/**
	 * Admin menu tree widget - uses Stimulus controllers.
	 */
	public const string __ADMIN_ADMINMENU = '
		JSTREE,
		HTMX,
		STIMULUS_LOADER,
	';

	/**
	 * Main menu tree widget - uses Stimulus controllers.
	 */
	public const string __ADMIN_MAINMENU = '
		JSTREE,
		HTMX,
		STIMULUS_LOADER,
	';

	/**
	 * Footer menu tree widget - uses Stimulus controllers.
	 */
	public const string __ADMIN_FOOTERMENU = '
		JSTREE,
		HTMX,
		STIMULUS_LOADER,
	';

	/**
	 * Role selector widget - uses Stimulus controllers.
	 */
	public const string __ADMIN_ROLE_SELECTOR = '
		JSTREE,
		STIMULUS_LOADER,
	';

	/**
	 * Usergroup selector widget - uses Stimulus controllers.
	 */
	public const string __ADMIN_USERGROUP_SELECTOR = '
		JSTREE,
		STIMULUS_LOADER,
	';
}
