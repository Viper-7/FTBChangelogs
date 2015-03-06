<?php
class ChangelogAdmin extends ModelAdmin {
	public static $allowed_actions = array(
	);

	public static $managed_models = array(
		'Pack',
		'Mod',
	);

	public static $url_segment = 'changelogs';
	public static $menu_title = 'Changelogs';
	public static $menu_icon = 'framework/admin/images/menu-icons/16x16/network.png';

	public $showImportForm = false;
}
