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
#[\AllowDynamicProperties]
class Multiple extends Common {
	private $restoreModules;

	public function process($skiphooks=false, $cliarguments = []) {
		if(!file_exists($this->file)) {
			throw new \Exception(sprintf(_('%s does not exist'),$this->file));
		}

		$this->log(_("Extracting Backup"));
		$this->extractFile();

		$restoreData = $this->getMasterManifest();
		$bkinfo = $restoreData['backupInfo'];
		if(isset($bkinfo['prere_hook']) && strlen(trim((string) $bkinfo['prere_hook']))> 1){
			$this->log(sprintf('Executing Pre Restore Hook: %s',$bkinfo['prere_hook']));
			exec($bkinfo['prere_hook']);
		}
		if(isset($restoreData['processorder'])){
			$restoreModules = $restoreData['processorder'];
		} else {
			$restoreModules = $restoreData['modules'];
		}
		if (!is_array($restoreModules)) {
			$this->log(_("Provided backup file does not contain multiple modules. May be this is a single module backup file so please re-try with '--restoresingle' option to restore the backup from the CLI "));
			exit;
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

		if(!is_null($this->specificRestores)) {
			$this->log(sprintf(_("Only Restoring %s"),implode(",",$this->specificRestores)),'WARNING');
			$restoreModules = array_filter($restoreModules, fn($arr) => in_array($arr['module'],$this->specificRestores));
		}

		foreach($restoreModules as $mod) {
			if($mod['module'] == 'certman' && $bkinfo['warmspareenabled'] == 'yes' && $bkinfo['warmspare_cert'] =='yes' ){
				$this->log(_('Skipping CertMan module Restore , Warmspare skip Certificate enabled'),'INFO');
				continue;
			}

			if(isset($cliarguments['ignoremodules']) && is_array($cliarguments['ignoremodules']) && count($cliarguments['ignoremodules'])> 0) {
				if(in_array($mod['module'],$cliarguments['ignoremodules'])) {
					$this->log(sprintf(_("MODULE SKIPED %s"),$mod['module']),'INFO');
					continue;
				}
			}
			$this->log(sprintf(_("Processing %s"),$mod['module']),'INFO');
			
			try {
				$this->processModule($mod['module'],$mod['version'],$cliarguments);
			} catch(\Exception $e) {
				$this->log($e->getMessage(). ' on line '.$e->getLine().' of file '.$e->getFile(),'ERROR');
				$this->log($e->getTraceAsString());
				$this->addError($e->getMessage(). ' on line '.$e->getLine().' of file '.$e->getFile());
				continue;
			}
			$this->log("",'INFO');

		}
		//end of all modules restore so unlock it
		$this->log(_('Restore processing for modules are finished successfully'));
		$this->setCustomFiles();
		$this->setRestoreEnd();
		$this->displayportschanges();
		$metadata = $this->getMasterManifest();
		$backupinfo = $metadata['backupInfo'];
		if(isset($backupinfo['postre_hook']) && strlen(trim((string) $backupinfo['postre_hook']))> 1){
			$this->log(sprintf('Executing Post Restore Hook: %s',$backupinfo['postre_hook']));
			exec($backupinfo['postre_hook']);
		}
		if ($backupinfo['warmspareenabled'] == 'yes') {
			if($backupinfo['warmspare_remoteapply'] =='yes') {
				do_reload();
			}
		} else {
			do_reload();
			$this->log(_('Reloading...... DONE'));
		}
		$rmcommand = "rm -rf $this->tmp";
		shell_exec($rmcommand);
		$this->freepbx->Backup->postrestoreModulehook($this->transactionId,$backupinfo);
		if($skiphooks==false){
			$this->log(_('Running Post Restore Hooks.. Please note that hook will restart httpd service so please refresh your page (using new ports) '));
			$this->postRestoreHooks();
		}else {
			$this->log(_('Skipped Post Restore Hooks..'));
		}
		$this->log(_('Running Post Restore Hooks DONE'));
		$this->log(_('Finished'));
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
		if ([] === $arr) return false;
		return array_keys($arr) !== range(0, (is_countable($arr) ? count($arr) : 0) - 1);
	}

}
