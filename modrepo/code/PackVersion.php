<?php
class PackVersion extends DataObject {
	public static $db = array(
		'Version' => 'Varchar(255)',
		'Changelog' => 'Text',
		'Populated' => 'Boolean',
		'EditedChangelog' => 'Text',
	);
	
	public static $has_one = array(
		'Pack' => 'Pack',
		'Minetweaker' => 'File',
	);

	public static $old_versions = array();
	
	public static $many_many = array(
		'ModVersion' => 'ModVersion'
	);
	
	public static $summary_fields = array(
		'Version' => 'Version',
		'Pack.Name' => 'Name',
	);

	public function getTitle() {
		return $this->Version;
	}

	public static $has_written = false;

	public function PreviousVersion() {
		return DataObject::get_one('PackVersion', 'PackID = ' . intval($this->PackID) . ' AND Version < \'' . Convert::raw2sql($this->Version) . '\'', true, 'Version DESC');
	}
	
	public function getChanges() {
		return self::get_changes($this->PreviousVersion(), $this);
	}

	public function onAfterWrite() {
		if(self::$has_written) Controller::curr()->redirectBack();
		self::$has_written = true;
	}

	public function onBeforeWrite() {
		parent::onBeforeWrite();

		if(self::$has_written) return;

		if($this->Populated && isset($_POST['BatchChangelog']) && is_array($val = $_POST['BatchChangelog'])) {
			foreach($val as $modid => $changelog) {
				$mv = DataObject::get_by_id('ModVersion', $modid);
				$mv->Changelog = $changelog;
				$mv->write();
			}
		}

		if(!empty($_POST['Minetweaker']['Files'][0])) {
			$id = $_POST['Minetweaker']['Files'][0];

			$file = DataObject::get_by_id('File', $id);
			$content = file_get_contents($path = $file->getFullPath());
			$this->MinetweakerID = null;
			$file->delete();
			$this->doMinetweaker($content);
		} 

		$prev = $this->PreviousVersion();

		// Parse minetweaker data into mod changelogs
		if(!$this->Populated) {
			if($prev) {
				foreach($this->ModVersion() as $mod) {
					$old = $prev->ModVersion('ModID=' . intval($mod->ModID));
					if($old->Count()) {
						$old = $old->First();
						if($old->ID == $mod->ID) continue;

						$changes = ModVersion::get_changes($old, $mod);
						$changelog = '';

						foreach($changes['Added'] as $item) {
							$changelog .= "Added {$item->Name}\r\n";
						}

						foreach($changes['Removed'] as $item) {
							$changelog .= "Removed {$item->Name}\r\n";
						}
				
						$mod->Changelog = trim($changelog);
						$mod->write();

						if($changelog) {
							$this->Populated = true;
						}
					}
				}
			}
		}


		// Parse mod list.txt into pack changelog
		$changes = PackVersion::getChanges($prev, $this);
		$changelog = '';

		foreach($changes['Added'] as $mod) {
			$changelog .= "Added {$mod->Name} {$mod->Version}\r\n";
		}

		foreach($changes['Updated'] as $mod) {
			$oldVersion = PackVersion::$old_versions[$mod->ModID];
			
			$changelog .= "Updated {$mod->Name} from {$oldVersion} to {$mod->Version}\r\n";

			if($mod->Changelog) {
				foreach(explode("\n", $mod->Changelog) as $line) {
					$changelog .= "    " . trim($line) . "\r\n";
				}
			}
		}

		foreach($changes['Removed'] as $mod) {
			$changelog .= "Removed {$mod->Name}\r\n";
		}

		$this->Changelog = trim($changelog);
	}

	public function doMinetweaker($content) {
		$modversions = array();

		if(preg_match_all('/^<(.+?):(.+?)>\s+--?\s+(.+)$/m', $content, $matches, PREG_SET_ORDER)) {
			foreach($matches as $match) {
				list($whole, $modid, $internal, $name) = $match;

				$mod = DataObject::get_one('Mod', 'ModId=\'' . Convert::raw2sql(trim($modid)) . '\'');
				if(!$mod) {
					$mod = new Mod();
					$mod->ModId = $modid;
					$mod->write();
				}

				if($name == 'null' || trim($name) == 'Name could not be retrieved due to an error: java.lang.NullPointerException')
					$name = ucwords(preg_replace('/^<(.+)>$|^(.+)\|.+?$/m', '$1', str_replace(array('.', '<', '>'), ' ', str_replace('tile.','',$internal))));

				$item = DataObject::get_one('Item', 'InternalName=\'' . Convert::raw2sql(trim($internal)) . '\' AND ModID=' . intval($mod->ID) . ' AND PackID=' . intval($this->PackID));
				
				if(!$item) {
					$item = DataObject::get_one('Item', 'Name=\'' . Convert::raw2sql(trim($name)). '\' AND ModID=' . intval($mod->ID) . ' AND PackID=' . intval($this->PackID));
				}

				if(!$item) {
					$item = new Item();
					$item->Name = $name;
					$item->InternalName = $internal;
					$item->ModID = $mod->ID;
					$item->PackID = $this->PackID;
					$item->write();
				}

				if(!isset($modversions[$mod->ID])) {
					$version = $this->ModVersion('ModID=' . $mod->ID);
					
					if($version->Count()) {
						$version = $version->First();
						$modversions[$mod->ID] = $version;
					}
				} else {
					$modversions[$mod->ID]->Item()->add($item);
				}
			}
		}
	}
	
	public function getCMSFields() {
		if($this->Version == 'new upload') {
			return new FieldList(new TabSet('Root', new Tab('Main', new TextField('Version'))));
		} else {
			$fields = parent::getCMSFields();
		
			if($this->Changelog) {
				$changelog = $fields->fieldByName('Root.Main.Changelog');
				$changelog->setRows(12);
				$changelog->setTitle('Generated<br>Changelog');
			} else {
				$fields->removeByName('Changelog');
			}

			$edited = $fields->fieldByName('Root.Main.EditedChangelog');
			$edited->setRows(12);
			$edited->setTitle('Changelog');
			$fields->removeByName('EditedChangelog');
			$fields->addFieldToTab('Root.Main', $edited, 'Changelog');

			$minetweaker = $fields->fieldByName('Root.Main.Minetweaker');
			$fields->removeByName('Minetweaker');
			$minetweaker->setTitle('minetweaker.log');

			$minetweaker->getValidator()->setAllowedExtensions(array('log'));
			$fields->addFieldToTab('Root.Minetweaker', $minetweaker);

			$fields->fieldByName('Root.Main')->setTitle('Pack Version');
			$fields->fieldByName('Root.ModVersion')->setTitle('Mods');
			$config = $fields->fieldByName('Root.ModVersion.ModVersion')->getConfig();
			$config->removeComponentsByType('GridFieldAddNewButton');
			$config->removeComponentsByType('GridFieldAddExistingAutocompleter');
			$config->removeComponentsByType('GridFieldDeleteAction');

			$changes = $this->getChanges();

			foreach($changes['Updated'] as $mod) {
				$fields->addFieldToTab('Root.Changelogs', new TextareaField('BatchChangelog[' . $mod->ID . ']', $mod->Name . '<br>' . $mod->Version, $mod->Changelog));
			}

			return $fields;
		}
	}
	
	public static function get_changes($oldVersion, $newVersion) {
		if(!$oldVersion && $newVersion) return array('Updated' => array(), 'Added' => $newVersion->ModVersion('', 'Name'), 'Removed' => array());
		if(!$newVersion->ID || !$oldVersion->ID) return array('Updated' => array(), 'Added' => array(), 'Removed' => array());

		$oldmods = array();
		$newmods = array();
		$versions = array();
		$versionchange = array();
		$mods = array();

		foreach($oldVersion->ModVersion() as $mod) {
			if(!isset($mods[$mod->ModID])) $mods[$mod->ModID] = $mod->Mod();
			if(!$mods[$mod->ModID]->HideInChangelogs) $oldmods[] = $mod->ModID;

			$versions[$mod->ModID] = $mod->Version;
		}

		foreach($newVersion->ModVersion() as $mod) {
			if(!isset($mods[$mod->ModID])) $mods[$mod->ModID] = $mod->Mod();
			if(!$mods[$mod->ModID]->HideInChangelogs) $newmods[] = $mod->ModID;

			if(isset($versions[$mod->ModID]) && $versions[$mod->ModID] != $mod->Version) {
				$versionchange[] = $mod->ModID;
				self::$old_versions[$mod->ModID] = $versions[$mod->ModID];
			}
		}

		$updated = implode(',', array_intersect(array_intersect($oldmods, $newmods), $versionchange));
		$added = implode(',', array_diff($newmods, $oldmods));
		$removed = implode(',', array_diff($oldmods, $newmods));

		return array(
			'Updated' => $newVersion->ModVersion($updated ? 'ModVersion.ModID IN (' . $updated . ')' : '1=0'),
			'Added'   => $newVersion->ModVersion($added   ? 'ModVersion.ModID IN (' . $added . ')' : '1=0'),
			'Removed' => $oldVersion->ModVersion($removed ? 'ModVersion.ModID IN (' . $removed . ')' : '1=0')
		);
	}
}
