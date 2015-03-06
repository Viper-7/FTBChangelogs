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

class Pack extends DataObject {
	public static $db = array(
		'Name' => 'Varchar(255)',
	);
	
	public static $has_one = array(
		'AddVersion' => 'File',
	);
	
	public static $has_many = array(
		'PackVersion' => 'PackVersion',
	);
	
	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$version = $fields->fieldByName('Root.PackVersion.PackVersion');
		if($version) {
			$config = $version->getConfig();
			$config->removeComponentsByType('GridFieldAddNewButton');

			$fields->fieldByName('Root.Main.AddVersion')->setTitle('Create new version<br>from Mod List.txt');
		} else {
			$fields->removeByName('AddVersion');
		}
		
		return $fields;
	}

	public function onBeforeWrite() {
		parent::onBeforeWrite();
		
		if(isset($_POST['AddVersion']['Files']) && $id = $_POST['AddVersion']['Files'][0]) {
			$file = DataObject::get_by_id('File', $id);
			$content = file_get_contents($path = $file->getFullPath());
			$this->AddVersionID = null;
			$file->delete();
			$this->doAddVersion($content);
		}
	}
	
	public function doAddVersion($content) {
		$pv = new PackVersion();
		$pv->PackID = $this->ID;
		$pv->Version = 'new upload';
		$pv->write();
		
		if(preg_match_all('/^\s*(.+)\s+\((.+?)\)\s+\|\s+Version:\s+(.+)\s+\|\s+Loaded From\s+(.+)\s+on\s+(.+)\s+\|\s+Website:\s+(.+?)\s*$/m', $content, $matches, PREG_SET_ORDER)) {
			foreach($matches as $match) {
				list($whole, $name, $modid, $ver, $filename, $container, $website) = $match;


				if(ctype_alpha($ver) && preg_match('/^[.\d]+$/', $name)) {
					list($name, $ver) = array($ver, $name);
				}

				if(in_array($ver, array('${version}', '@VERSION@', '@MOD_VERSION@', 'unspecified'))) {
					if(preg_match('/^(.+)[-_\s\.](\d+\.\d+\.\d+)[-_\s\.](.+)(?:[-_\s\.]\w+)?\.jar$/i', $filename, $match)) {
						list($whole, $file_modname, $file_mcver, $file_ver) = $match;
						$ver = $file_ver;
					} elseif(preg_match('/^(.+)[-_\s\.](.+)\.jar$/i', $filename, $match)) {
						list($whole, $file_modname, $file_ver) = $match;
						$ver = $file_ver;
					}
				}

				$mod = DataObject::get_one('Mod', 'ModId=\'' . Convert::raw2sql(trim($modid)) . '\'');
				if(!$mod) {
					$mod = new Mod();
					$mod->ModId = $modid;
					$mod->write();
				}
				
				$version = $mod->ModVersion('Version = \'' . Convert::raw2sql(trim($ver)) . '\'');
				if($version->Count()) {
					$version = $version->First();
				} else {
					$version = new ModVersion();
					$version->ModID = $mod->ID;
					$version->Name = $name;
					$version->Version = $ver;
					$version->Filename = $filename;
					$version->Container = $container;
					$version->Website = $website;
					$version->write();
				}
				
				$pv->ModVersion()->add($version);
			}
		}
	}
}

class PackVersion extends DataObject {
	public static $db = array(
		'Version' => 'Varchar(255)',
		'Changelog' => 'Text',
		'ModList' => 'Text',
	);
	
	public static $has_one = array(
		'Pack' => 'Pack',
		'Minetweaker' => 'File',
	);
	
	public static $many_many = array(
		'ModVersion' => 'ModVersion'
	);
	
	public static $summary_fields = array(
		'Version' => 'Version'
	);

	public function getTitle() {
		return $this->Version;
	}

	public function PreviousVersion() {
		return DataObject::get_one('PackVersion', 'Version < \'' . Convert::raw2sql($this->Version) . '\'', true, 'Version DESC');
	}
	
	public static function get_changes($oldVersion, $newVersion) {
		if(!$oldVersion && $newVersion) return array('Updated' => array(), 'Added' => $newVersion->ModVersion('', 'Name'), 'Removed' => array());
		if(!$newVersion->ID || !$oldVersion->ID) return array('Updated' => array(), 'Added' => array(), 'Removed' => array());

		$oldmods = array();
		$newmods = array();
		$versions = array();
		$versionchange = array();

		foreach($oldVersion->ModVersion() as $mod) {
			$oldmods[] = $mod->ModID;
			$versions[$mod->ModID] = $mod->Version;
		}

		foreach($newVersion->ModVersion() as $mod) {
			$newmods[] = $mod->ModID;
			if(isset($versions[$mod->ModID]) && $versions[$mod->ModID] != $mod->Version) {
				$versionchange[] = $mod->ModID;
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

	public function getChanges() {
		return self::get_changes($this->PreviousVersion(), $this);
	}

	public function onBeforeWrite() {
		parent::onBeforeWrite();

		if(isset($_POST['BatchChangelog']) && is_array($val = $_POST['BatchChangelog'])) {
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
                

		$prev = $this->PreviousVersion();

		if($prev) {
		foreach($this->ModVersion() as $mod) {
			if(trim($mod->Changelog) == '') {
				$old = $prev->ModVersion('ModID=' . intval($mod->ModID));
				if($old->Count()) {
					$old = $old->First();
					if($old->ID == $mod->ID) continue;

				$changes = ModVersion::get_changes($old, $mod);
				$changelog = '';

				foreach($changes['Added'] as $item) {
					$changelog .= "\r\nAdded {$item->Name}";
				}

				foreach($changes['Removed'] as $item) {
					$changelog .= "\r\nRemoved {$item->Name}";
				}

				$mod->Changelog = trim($changelog);
				$mod->write();
				}
			}
		}
		}
		}
                       $changes = $this->getChanges();
                       $changelog = '';

			foreach($changes['Added'] as $mod) {
				$changelog .= "\r\nAdded {$mod->Name} {$mod->Version}";
			}

                        foreach($changes['Updated'] as $mod) {
                                $changelog .= "\r\nUpdated {$mod->Name} to {$mod->Version}";

                                if($mod->Changelog) {
                                        foreach(explode("\n", $mod->Changelog) as $line) {
                                                $changelog .= "\r\n    " . trim($line);
                                        }
					$changelog .= "\r\n";
                                }
                        }

			foreach($changes['Removed'] as $mod) {
				$changelog .= "\r\nRemoved {$mod->Name}";
			}

                        $this->Changelog = trim($changelog);

		$modlist = '';
		foreach($this->ModVersion() as $mod) {
			$modlist .= "{$mod->Name} {$mod->Version}\r\n";
		}
		$this->ModList = trim($modlist);


		
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
			
				if($name == 'null') $name = ucfirst($internal);

				$item = DataObject::get_one('Item', 'InternalName=\'' . Convert::raw2sql(trim($internal)) . '\' AND ModID=' . intval($mod->ID));
				if(!$item) {
					$item = DataObject::get_one('Item', 'Name=\'' . Convert::raw2sql(trim($name)). '\' AND ModID=' . intval($mod->ID));
				}

				if(!$item) {
					$item = new Item();
					$item->Name = $name;
					$item->InternalName = $internal;
					$item->ModID = $mod->ID;
					$item->write();
				}

                                if(!isset($modversions[$mod->ID])) {
					$version = $this->ModVersion('ModID=' . $mod->ID);
	                                if($version->Count()) {
        	                                $version = $version->First();
						$modversions[$mod->ID] = $version;
					}
				}

				if(isset($modversions[$mod->ID]))
					$modversions[$mod->ID]->Item()->add($item);
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
				$changelog->setRows(20);
				$changelog->setTitle('Generated<br>Changelog');
			} else {
				$fields->removeByName('Changelog');
				$fields->removeByName('ModList');
			}

			$minetweaker = $fields->fieldByName('Root.Main.Minetweaker');
			$fields->removeByName('Minetweaker');
			$minetweaker->setTitle('minetweaker.log');

			$minetweaker->getValidator()->setAllowedExtensions(array('log'));
			$fields->addFieldToTab('Root.Items', $minetweaker);

			$fields->fieldByName('Root.Main')->setTitle('Pack Version');
			$fields->fieldByName('Root.ModVersion')->setTitle('Mods');

			$changes = $this->getChanges();

			foreach($changes['Updated'] as $mod) {
				$fields->addFieldToTab('Root.Changelogs', new TextareaField('BatchChangelog[' . $mod->ID . ']', $mod->Name . '<br>' . $mod->Version, $mod->Changelog));
			}

			return $fields;
		}
	}
}

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

class Mod extends DataObject {
	public static $db = array(
		'ModId' => 'Varchar(255)',
	);
	
	public static $has_many = array(
		'ModVersion' => 'ModVersion',
	);

	public static $summary_fields = array(
		'ModId' => 'ModId',
	);

	public static $indexes = array(
		'ModId' => true
	);
	
	public function getName() {
		return $this->ModId;
	}
}

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
