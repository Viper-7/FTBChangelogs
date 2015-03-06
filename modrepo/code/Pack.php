<?php
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

	public function canDelete($member = NULL) {
		return $this->PackVersion()->Count() == 0; 
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
	
	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$version = $fields->fieldByName('Root.PackVersion.PackVersion');
		if($version) {
			$config = $version->getConfig();
			$config->removeComponentsByType('GridFieldAddNewButton');
			$config->removeComponentsByType('GridFieldAddExistingAutocompleter');

			$fields->fieldByName('Root.Main.AddVersion')->setTitle('Create new version<br>from Mod List.txt');
		} else {
			$fields->removeByName('AddVersion');
		}
		
		return $fields;
	}
}
