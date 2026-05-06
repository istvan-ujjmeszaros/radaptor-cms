<?php

// The pattern should be Libraries+ThemeName (Common holds the generally reusable libraries)
class LibrariesCommon
{
	public const string __ADMIN_SITE = '
        COMMON,
        JQUERY_UI_10,
        JQUERY_HOVERINTENT,
        /assets/packages/themes/so-admin/admin-site/css-reset/html5-boilerplate.css,
        /assets/packages/themes/so-admin/admin-site/admin-site/admin-site.css,
        /assets/packages/themes/so-admin/admin-site/buttons.css,
    ';
	public const string _ADMIN_DROPDOWN = '
        COMMON,
        /assets/_common/libraries/anylinkmenu/anylinkmenu.css,
        /assets/_common/libraries/anylinkmenu/anylinkmenu.js,
        /assets/packages/themes/so-admin/admin-site/admin_dropdown.css,
    ';
	public const string _SDUI_STATUSMESSAGE = '
        /assets/_common/css/sdui-status.css,
    ';
	public const string __ADMIN_USERLIST = '
        DATATABLES_FILTER,
        /assets/packages/themes/so-admin/admin-site/js/widgettype.userList.js,
    ';
	public const string __ADMIN_BLOGLIST = '
        DATATABLES_FILTER,
        /assets/packages/themes/so-admin/admin-site/js/widgettype.blogList.js,
    ';
	public const string __ADMIN_RESOURCE_ACL_SELECTOR = '
        DATATABLES_FILTER,
        DATATABLES_CUSTOMAPI,
        /assets/packages/themes/so-admin/admin-site/js/widgettype.resourceAclSelector.js,
    ';
	public const string __ADMIN_USERGROUPS = '
        JSTREE,
        /assets/packages/themes/so-admin/admin-site/js/jstree.usergroups.js,
    ';
	public const string __ADMIN_USERGROUP_SELECTOR = '
        JSTREE,
        /assets/packages/themes/so-admin/admin-site/js/jstree.usergroupSelector.js,
    ';
	public const string __ADMIN_ROLES = '
        JSTREE,
        /assets/packages/themes/so-admin/admin-site/js/jstree.roles.js,
    ';
	public const string __ADMIN_ROLE_SELECTOR = '
        JSTREE,
        /assets/packages/themes/so-admin/admin-site/js/jstree.roleSelector.js,
    ';
	public const string __ADMIN_RESOURCES = '
        JSTREE,
        /assets/packages/themes/so-admin/admin-site/js/jstree.resources.js,
    ';
	public const string __ADMIN_MAINMENU = '
        JSTREE,
        /assets/packages/themes/so-admin/admin-site/js/jstree.mainmenu.js,
    ';
	public const string __ADMIN_FOOTERMENU = '
        JSTREE,
        /assets/packages/themes/so-admin/admin-site/js/jstree.footermenu.js,
    ';
	public const string __ADMIN_ADMINMENU = '
        JSTREE,
        /assets/packages/themes/so-admin/admin-site/js/jstree.adminmenu.js,
    ';
	public const string __COMMON_ADMIN = '
        /assets/packages/themes/radaptor-portal-admin/css/edit-mode.css,
        /assets/packages/themes/radaptor-portal-admin/js/edit-mode.js,
    ';
	public const string COMMON = '
        JQUERY,
        QUERY,
        GRITTER,
        /assets/_common/libraries/common.js,
    ';
	public const string JQUERY = '
        /assets/_common/libraries/jquery/jquery-1.12.4.min.js,
    ';
	public const string TINYMCE = '
        JQUERY,
        /assets/_common/libraries/tiny_mce/jquery.tinymce.js,
        /assets/_common/libraries/tiny_mce/tiny_mce.js,
    ';

	/* COMMON calls this one */
	public const string QUERY = '
        JQUERY,
        /assets/_common/libraries/jquery/jquery-migrate-1.4.1.min.js,
        /assets/_common/libraries/jquery-ba-bbq/1.2.1/jquery.ba-bbq.min.js,
    ';
	public const string GRITTER = '
        JQUERY,
        COMMON,
        /assets/_common/libraries/jquery-gritter/1.7.1/css/jquery.gritter.css,
        /assets/_common/libraries/jquery-gritter/1.7.1/jquery.gritter.js,
    ';
	public const string JQUERY_UI = '
        JQUERY,
        /assets/_common/libraries/jquery-ui/1.10.0/js/jquery-ui-1.10.0.custom.min.js,
        /assets/_common/libraries/jquery-ui/1.10.0/css/smoothness/jquery-ui-1.10.0.custom.css,
    ';
	public const string JQUERY_UI_10 = '
        JQUERY,
        /assets/_common/libraries/jquery-ui/1.10.0/js/jquery-ui-1.10.0.custom.min.js,
        /assets/_common/libraries/jquery-ui/1.10.0/css/smoothness/jquery-ui-1.10.0.custom.css,
    ';
	public const string JQUERY_COLOR = '
        JQUERY,
        /assets/_common/libraries/jquery-color/jquery.color.js,
    ';

	/* ___ADMIN_SITE is calling this */
	public const string JQUERY_HOVERINTENT = '
        JQUERY,
        /assets/_common/libraries/jquery-hoverintent/1.5.1/jquery.hoverIntent.minified.js,
    ';
	public const string QTIP = '
        JQUERY,
        /assets/_common/libraries/jquery-qtip/2.0.0pre/jquery.qtip.min.js,
        /assets/_common/libraries/jquery-qtip/2.0.0pre/jquery.qtip.css,
    ';
	public const string TIPPY = '
        js:https://unpkg.com/@popperjs/core@2/dist/umd/popper.min.js,
        js:https://unpkg.com/tippy.js@6/dist/tippy-bundle.umd.min.js,
        css:https://unpkg.com/tippy.js@6/dist/tippy.css,
    ';
	public const string DROPZONE = '
        css:https://unpkg.com/dropzone@5.9.3/dist/min/dropzone.min.css,
        js:https://unpkg.com/dropzone@5.9.3/dist/min/dropzone.min.js,
    ';
	public const string DATATABLES = '
        JQUERY,
        /assets/_common/libraries/jquery-datatables/1.9.1/media/css/datatables-colreorder.css,
        /assets/_common/libraries/jquery-datatables/1.9.1/media/css/datatables-colvis.css,
        /assets/_common/libraries/jquery-datatables/1.9.1/media/css/datatables.css,
        /assets/_common/libraries/jquery-datatables/1.9.1/media/js/jquery.dataTables.js,
        /assets/toptal-todo/datatables/dataTables.bootstrap.css,
        /assets/toptal-todo/datatables/dataTables.bootstrap.js,
	';

	public const string DATATABLES_FILTER = '
        DATATABLES,
        /assets/_common/libraries/jquery-datatables/1.9.1/datatables_filter.js,
    ';
	public const string DATATABLES_CUSTOMAPI = '
        DATATABLES,
        /assets/_common/libraries/jquery-datatables/1.9.1/datatables.customApi.js
    ';

	/* JSTREE calling this */
	public const string HOTKEYS = '
        JQUERY,
        /assets/_common/libraries/jquery-hotkeys/0.8/jquery.hotkeys.js,
    ';

	/* JSTREE calling this */
	public const string COOKIE = '
        JQUERY,
        /assets/_common/libraries/jquery-cookie/jquery.cookie.js,
    ';

	/* JSTREE calling this */
	public const string WIDGETTYPES_COMMON = '
        /assets/shared/widgetTypes/widgettypes.js
    ';
	public const string JSTREE = '
        JQUERY,
        WIDGETTYPE_JSTREE,
        HOTKEYS,
        COOKIE,
        WIDGETTYPES_COMMON,
        */assets/_common/libraries/jquery-jstree/1.0-rc3/jquery.jstree.js,
        /assets/_common/libraries/jquery-jstree/1.0-rc3uj/jquery.jstree.js,
    ';
	public const string WIDGETTYPE_FORM = '
        /assets/shared/widgetTypes/form.js,
    ';

	/* JSTREE calling this — file no longer exists, overridden to empty in all active themes */
	public const string WIDGETTYPE_JSTREE = '';

	public const string WIDGET_EDIT = '
        JQUERY,
        JQUERY_UI_10,
        COMBOBOX,
        QTIP,
        /assets/shared/widgetTypes/widget-edit.js,
    ';
	public const string COMBOBOX = '
        JQUERY,
        /assets/_common/libraries/jquery-ui/jquery.ui.combobox.js,
    ';
	public const string SYSTEMMESSAGES = '
        COMMON,
    ';
	public const string CKEDITOR = '
        /assets/_common/libraries/ckeditor/3.6.3/ckeditor.js,
        /assets/_common/libraries/ckeditor/3.6.3/adapters/jquery.js,
    ';
	public const string CKEDITOR_SOURCE = '
        /assets/_common/libraries/ckeditor/3.6.3/ckeditor_source.js,
        /assets/_common/libraries/ckeditor/3.6.3/adapters/jquery.js,
    ';

	public const string CALENDAR = '
        COMMON,
        /assets/_common/libraries/jscalendar/1.51/calendar-da.css,
        /assets/_common/libraries/jscalendar/1.51/calendar_stripped.js,
        /assets/_common/libraries/jscalendar/1.51/calendar-setup_stripped.js,
        /assets/_common/libraries/jscalendar/1.51/lang/calendar-hu.js,
    ';

	public const string CODEMIRROR = '
        /assets/_common/libraries/codemirror/3.0/lib/codemirror.css,
        /assets/_common/libraries/codemirror/3.0/lib/codemirror.js,
        /assets/_common/libraries/codemirror/3.0/lib/util/matchbrackets.js,
        /assets/_common/libraries/codemirror/3.0/mode/xml/xml.js,
        /assets/_common/libraries/codemirror/3.0/mode/javascript/javascript.js,
        /assets/_common/libraries/codemirror/3.0/mode/css/css.js,
        /assets/_common/libraries/codemirror/3.0/mode/clike/clike.js,
        /assets/_common/libraries/codemirror/3.0/mode/php/php.js,
        /assets/_common/libraries/codemirror/3.0/mode/htmlmixed/htmlmixed.js,
        ';
}
