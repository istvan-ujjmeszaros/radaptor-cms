<?php

enum IconNames: string
{
	// Actions
	case ADD = 'add';
	case EDIT = 'edit';
	case DELETE = 'delete';
	case TRASH = 'trash';
	case VIEW = 'view';
	case LOOK = 'look';
	case CHOOSE = 'choose';

	// Navigation
	case DROPDOWN = 'dropdown';
	case WIDGET_UP = 'widget_up';
	case WIDGET_DOWN = 'widget_down';

	// File operations
	case UPLOAD = 'upload';
	case DOWNLOAD = 'download';

	// Form
	case FORM_SAVE = 'form_save';
	case FORM_CANCEL = 'form_cancel';
	case FORM_HELP = 'form_help';
	case FORM_ERROR = 'form_error';

	// Content
	case WIDGET_ADD = 'widget_add';
	case WIDGET_REMOVE = 'widget_remove';
	case WIDGET_INSERT = 'widget_insert';
	case WEBPAGE_ADD = 'webpage_add';
	case CONTENT_ADD = 'content_add';
	case PLUS = 'plus';

	// Folders
	case FOLDER = 'folder';
	case FOLDER_ADD = 'folder_add';

	// Users
	case USER = 'user';
	case USERGROUP = 'usergroup';
	case USERGROUP_ADD = 'usergroup_add';
	case SYSTEM_USERGROUP = 'system_usergroup';
	case PEOPLE = 'people';
	case ROLES = 'roles';
	case ROLE = 'role';
	case LOCK = 'lock';
	case LOGIN = 'login';

	// Status
	case STATUS_OK = 'status_ok';
	case STATUS_ERROR = 'status_error';
	case ALERT = 'alert';
	case WARNING = 'warning';
	case INFO = 'info';
	case ACCEPT = 'accept';
	case REMOVE = 'remove';
	case BUG = 'bug';
	case GEAR = 'gear';
	case COMMENT = 'comment';

	// Data
	case DATASHEET = 'datasheet';
	case CHART = 'chart';
	case CHECKLIST = 'checklist';
	case VERSIONS = 'versions';
	case COLUMN_WIDTH = 'column_width';
	case ALIGN = 'align';

	// Links
	case LINK = 'link';
	case LINK_OUT = 'link_out';
	case LINK_NONE = 'link_none';

	// Misc
	case ADMIN_WRENCH = 'admin_wrench';
	case MENUBAR = 'menubar';
	case HELP = 'help';
	case DATE = 'date';
	case DATETIME = 'datetime';
}
