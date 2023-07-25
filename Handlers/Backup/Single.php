<?php
/**
 * Copyright Sangoma Technologies 2018
 */
namespace FreePBX\modules\Backup\Handlers\Backup;
use Symfony\Component\Console\Output\ConsoleOutput;
use function FreePBX\modules\Backup\Json\json_decode;
use function FreePBX\modules\Backup\Json\json_encode;
use Monolog\Formatter;
/**
 * Class used for Single Module Backup
 */
class Single extends Common {

	use Email;
	
	public function setModule($module) {
		$this->module = $module;
	}

	public function process(){
		if(empty($this->module)) {
			throw new \Exception("Unknown Module!");
		}
		$moduleInfo = $this->freepbx->Modules->getInfo($this->module)[$this->module];
		if(empty($moduleInfo)) {
			throw new \Exception(sprintf(_('Unknown module %s'),$this->module));
		}

		//setup tarball format
		$tarfilename = sprintf('%s-%s%s-%s-%s', $this->module, date("Ymd-His-"), time(), $moduleInfo['version'], random_int(0, mt_getrandmax()));
		$targzname = sprintf('%s.tar.gz', $tarfilename);

		$this->setFilename($targzname);

		$tar = $this->openFile();

		$this->log(sprintf(_("Processing %s"),$this->module));
		$moddata = $this->processModule('singlebackup', ['rawname' => $this->module, 'ucfirst' => ucfirst((string) $this->module)]);

		if(!empty($moddata['dependencies'])) {
			$this->log("***"._("In single restores mode dependencies are NOT processed")."***",'WARNING');
		}

		$manifest = ['moddata' => $moddata, 'module' => $moduleInfo];

		$tar->addData('metadata.json', json_encode($manifest, JSON_PRETTY_PRINT));

		$this->closeFile();

		if(!empty($moddata['garbage'])) {
			$this->log(_("Cleaning up"));
			foreach($moddata['garbage'] as $item) {
				$this->log("\t".sprintf(_("Removing %s"),$item),'DEBUG');
				$this->fs->remove($item);
			}
			$this->log(_("Finished Cleaning up"));
		} else {
			$this->log(_("There was nothing to cleanup"));
		}

		$this->log(sprintf(_("Finished created backup file: %s"),$this->getFile()));
		return $this->getFile();
	}

}
