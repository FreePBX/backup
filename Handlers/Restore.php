<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 */
namespace FreePBX\modules\Backup\Handlers;
use FreePBX\modules\Backup\Modules as Module;
use FreePBX\modules\Backup\Models as Models;
use FreePBX\modules\Backup\Handlers as Handlers;
use Phar;
use PharData;
use InvalidArgumentException;
class Restore{
	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new InvalidArgumentException('Not given a BMO Object');
		}
		$this->FreePBX = $freepbx;
		$this->Backup = $freepbx->Backup;
		$webrootpath = $this->FreePBX->Config->get('AMPWEBROOT');
		$webrootpath = (isset($webrootpath) && !empty($webrootpath))?$webrootpath:'/var/www/html';
		define('WEBROOT', $webrootpath);
		define('BACKUPTMPDIR','/var/spool/asterisk/tmp');
	}
	public function process($backupFile, $jobid) {
		$this->Backup->fs->remove(BACKUPTMPDIR);
		$this->Backup->fs->mkdir(BACKUPTMPDIR);
		$phar = new \PharData($backupFile);
		$restoreData = $phar->getMetadata();
		if(isset($restoreData['processorder'])){
			$this->restoreModules = $restoreData['processorder'];
		}
		if(!isset($restoreData['processorder'])){
			$this->restoreModules = $restoreData['modules'];
		}
		$this->Backup->log($jobid,_("Extracting Backup"));
		$phar->extractTo(BACKUPTMPDIR);
		$errors = [];
		$warnings = [];
		$this->Backup->log($jobid,_("Running pre restore hooks"));
		$this->preHooks($jobid,$restoreData);
		foreach($this->restoreModules as $key => $value) {
			$modjson = BACKUPTMPDIR . '/modulejson/' . ucfirst($key) . '.json';
			if(!file_exists($modjson)){
				$msg = sprintf(_("Could not find a manifest for %s, skipping"),ucfirst($key));
				$this->Backup->log($jobid,$msg,'WARNING');

				$errors[] = $msg;
				continue;
			}
			$moddata = json_decode(file_get_contents($modjson), true);
			$restore = new Models\Restore($this->Backup->FreePBX, $moddata);
			$depsOk = $this->Backup->processDependencies($restore->getDependencies());
			if(!$depsOk){
				$errors[] = printf(_("Dependencies not resolved for %s Skipped"),$key);
				continue;
			}
			$modulehandler = new Handlers\FreePBXModule($this->FreePBX);
			\modgettext::push_textdomain($key);
			$this->Backup->log($jobid,sprintf(_("Running restore process for %s"),$key));
			$this->Backup->log($jobid,sprintf(_("Resetting the data for %s, this may take a moment"),$key));
			$backedupVer = $value;
			$modulehandler->reset($mod['name'],$backedupVer);
			$this->Backup->log($jobid,sprintf(_("Restoring the data for %s, this may take a moment"),$key));
			$class = sprintf('\\FreePBX\\modules\\%s\\Restore',ucfirst($key));
			$class = new $class($restore,$this->FreePBX,BACKUPTMPDIR);
			$class->runRestore($jobid);
			\modgettext::pop_textdomain();
		}
		$this->Backup->log($jobid,_("Running post restore hooks"));
		$this->postHooks($jobid,$restoreData);
        $this->Backup->fs->remove(BACKUPTMPDIR);
        \needreload();
		return $errors;
	}

	/**
	 * Get a list of modules that implement the restore method
	 * @return array list of modules
	 */
	public function getModules($force = false){
		//Cache
		if(isset($this->restoreMods) && !empty($this->restoreMods) && !$force) {
			return $this->restoreMods;
		}
		//All modules impliment the "backup" method so it is a horrible way to know
		//which modules are valid. With the autoloader we can do this magic :)
		$amodules = $this->FreePBX->Modules->getActiveModules();
		$validmods = [];
		foreach ($amodules as $module) {
			$bufile = WEBROOT . '/admin/modules/' . $module['rawname'].'/Restore.php';
			if(file_exists($bufile)){
				$validmods[] = $module;
			}
		}
		return $validmods;
	}
	public function preHooks($transactionId = '',$restoreData = []){
		$err = [];
		$restoreData = base64_encode(json_encode($restoreData,\JSON_PRETTY_PRINT));
		$args = escapeshellarg($transactionId).' '.$restoreData;
		$this->FreePBX->Hooks->processHooks($transactionId,$restoreData);
		$this->Backup->getHooks('restore');
		foreach($this->Backup->preRestore as $command){
			$cmd  = escapeshellcmd($command).' '.$args;
			exec($cmd,$out,$ret);
			if($ret !== 0){
				$errors[] = sprintf(_("%s finished with a non-zero status"),$cmd);
			}
		}
		unset($this->Backup->preRestore);
		return !empty($errors)?$errors:true;
	}
	public function postHooks($transactionId='',$restoreData=[]){
		$err = [];
		$restoreData = base64_encode(json_encode($restoreData,\JSON_PRETTY_PRINT));
		$args = escapeshellarg($transactionId).' '.$restoreData;
		$this->FreePBX->Hooks->processHooks($transactionId);
		$this->Backup->getHooks('restore');
		foreach($this->Backup->postRestore as $command){
			$cmd  = escapeshellcmd($command).' '.$args;
			exec($cmd,$out,$ret);
			if($ret !== 0){
				$errors[] = sprintf(_("%s finished with a non-zero status"),$cmd);
			}
		}
		unset($this->Backup->postRestore);
		return !empty($errors)?$errors:true;
	}

}
