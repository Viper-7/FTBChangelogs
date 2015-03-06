<?php
class Mod extends DataObject {
	public static $db = array(
		'ModId' => 'Varchar(255)',
		'HideInChangelogs' => 'Boolean',
	);
	
	public static $has_many = array(
		'ModVersion' => 'ModVersion',
	);

	public static $summary_fields = array(
		'ModId' => 'ModId',
		'HideInChangelogs.Nice' => 'Hide in Changelogs'
	);

	public static $indexes = array(
		'ModId' => true
	);

	public static $searchable_fields = array(
		'ModId'
	);
	
	public function getName() {
		return $this->ModId;
	}

	public function getTitle() {
		return $this->ModId;
	}
	
	public function canDelete($member = NULL) {
		return $this->ModVersion()->Count() == 0;
	}
}
