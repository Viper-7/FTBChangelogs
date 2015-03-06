<?php
class Item extends DataObject {
	public static $db = array(
		'Name' => 'Varchar(255)',
		'InternalName' => 'Varchar(255)',
	);

	public static $has_one = array(
		'Mod' => 'Mod'
	);

	public static $belongs_many_many = array(
		'ModVersion' => 'ModVersion'
	);


	public static $create_table_options = array(
		'MySQLDatabase' => 'ENGINE=MyISAM'
	);
}
