<?php
class PackPage extends Page {}
class PackPage_Controller extends Page_Controller {
	private static $url_handlers = array(
		'$ID//$OtherID' => 'view'
	);

	private static $allowed_actions = array('view');

	private $PackID;
	private $PackVersionID;
	
	public function view(SS_HTTPRequest $request) {
		$this->PackID = $request->param('ID');
		$this->PackVersionID = $request->param('OtherID');

		return $this->render();
	}

	public function getPacks() {
		$packs = array();
		foreach(DataObject::get('PackVersion', 'EditedChangelog IS NOT NULL') as $ver) {
			if(!isset($packs[$ver->PackID]))
				$packs[$ver->PackID] = $ver->PackID;
		}
		return DataObject::get('Pack', 'ID IN (' . implode(',', $packs) . ')');
	}

	public function getPackVersions() {
		if($this->PackID) {
			return DataObject::get_one('Pack', 'Name = \'' . Convert::raw2sql($this->PackID) . '\'')->PackVersion('EditedChangelog IS NOT NULL');
		}
	}

	public function getPackVersion() {
		if($this->PackVersionID)
			return DataObject::get_one('PackVersion', 'Version = \'' . Convert::raw2sql($this->PackVersionID) . '\'');
	}
}
