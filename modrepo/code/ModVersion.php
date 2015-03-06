<?php
class ModVersion extends DataObject {
	public static $db = array(
		'Name' => 'Varchar(255)',
		'Version' => 'Varchar(255)',
		'Filename' => 'Varchar(255)',
		'Container' => 'Varchar(255)',
		'Website' => 'Varchar(1024)',
		'Changelog' => 'Text',
	);
	
	public static $has_one = array(
		'Mod' => 'Mod',
	);

	public static $many_many = array(
		'Item' => 'Item',
	);
	
	public static $belongs_many_many = array(
		'PackVersion' => 'PackVersion',
	);

	public static $indexes = array(
		'Version' => true
	);

	public static $summary_fields = array(
		'Name' => 'Name',
		'Version' => 'Version',
		'Filename' => 'Filename',
	);

	public $OldVersion = '';

	public function canDelete($member = NULL) {
		return false;
	}

	public function getHideInChangelogs() {
		return $this->Mod()->HideInChangelogs;
	}


	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->removeByName('ModID');

		$config = GridFieldConfig_RecordEditor::create();
		$config->removeComponentsByType('GridFieldAddNewButton');
		$config->removeComponentsByType('GridFieldDeleteAction');

		$fields->fieldByName('Root.PackVersion.PackVersion')->setConfig($config);
		$fields->fieldByName('Root.Item.Item')->setConfig($config);
		return $fields;
	}

	public static function get_changes($oldVersion, $newVersion) {
		if(!$oldVersion && $newVersion) return array('Updated' => array(), 'Added' => $newVersion->Item(), 'Removed' => array());
		if(!$newVersion->ID || !$oldVersion->ID) return array('Updated' => array(), 'Added' => array(), 'Removed' => array());

		$olditems = array();
		$newitems = array();

		foreach($oldVersion->Item() as $item) {
			$olditems[] = $item->ID;
		}

		foreach($newVersion->Item() as $item) {
			$newitems[] = $item->ID;
		}

		$added = implode(',', array_diff($newitems, $olditems));
		$removed = implode(',', array_diff($olditems, $newitems));

		return array(
			'Added' => $newVersion->Item($added ? 'Item.ID IN (' . $added . ')' : '1=0'),
			'Removed' => $oldVersion->Item($removed ? 'Item.ID IN (' . $removed . ')' : '1=0')
		);
    }
}
