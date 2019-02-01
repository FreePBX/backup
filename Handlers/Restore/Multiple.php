<?php
/**
* Copyright Sangoma Technologies, Inc 2018
*/
namespace FreePBX\modules\Backup\Handlers\Restore;
use FreePBX\modules\Backup\Modules as Module;
use FreePBX\modules\Backup\Models as Models;
use FreePBX\modules\Backup\Handlers as Handlers;
use splitbrain\PHPArchive\Tar;
use function FreePBX\modules\Backup\Json\json_decode;
use function FreePBX\modules\Backup\Json\json_encode;
class Multiple extends Common {
	private $restoreModules;

	public function process() {
		if(!file_exists($this->file)) {
			throw new \Exception(sprintf(_('%s does not exist'),$this->file));
		}

		$this->log(_("Extracting Backup"));
		$this->extractFile();

		$restoreData = $this->getMasterManifest();

		if(isset($restoreData['processorder'])){
			$restoreModules = $restoreData['processorder'];
		} else {
			$restoreModules = $restoreData['modules'];
		}

		if($this->isAssoc($restoreModules)) {
			$remapedRestoreModules = [];
			foreach($restoreModules as $rawname => $version) {
				$remapedRestoreModules[] = [
					'module' => $rawname,
					'version' => $version
				];
			}
			$restoreModules = $remapedRestoreModules;
		}

		foreach($restoreModules as $mod) {
			if($mod['module'] === 'backup') {
				continue;
			}
			try {
				$this->processModule($mod['module'],$mod['version']);
			} catch(\Exception $e) {
				$this->log($e->getMessage(). ' on line '.$e->getLine().' of file '.$e->getFile(),'ERROR');
				$this->log($e->getTraceAsString());
				continue;
			}

		}
		$this->log(_('Finished'));
		needreload();
	}

	/**
	* Get a list of modules that implement the restore method
	* @return array list of modules
	*/
	public function getModules(){
		//All modules impliment the "backup" method so it is a horrible way to know
		//which modules are valid. With the autoloader we can do this magic :)
		$webrootpath = $this->freepbx->Config->get('AMPWEBROOT');
		$moduleInfo = $this->freepbx->Modules->getInfo(false,MODULE_STATUS_ENABLED);
		$validmods = [];
		foreach ($moduleInfo as $rawname => $data) {
			$bufile = $webrootpath . '/admin/modules/' . $module['rawname'].'/Restore.php';
			if(file_exists($bufile)){
				$validmods[] = $module;
			}
		}
		return $validmods;
	}

	private function isAssoc($arr) {
		if (array() === $arr) return false;
		return array_keys($arr) !== range(0, count($arr) - 1);
	}

}
