<?php
/**
* Copyright Sangoma Technologies, Inc 2018
*/
namespace FreePBX\modules\Backup\Handlers;
use FreePBX\modules\Backup\Modules as Module;
use FreePBX\modules\Backup\Models as Models;
use FreePBX\modules\Backup\Handlers as Handlers;
use splitbrain\PHPArchive\Tar;
use function FreePBX\modules\Backup\Json\json_decode;
use function FreePBX\modules\Backup\Json\json_encode;
class Multiple extends Common {
	public function __construct($freepbx, $file, $transactionId, $pid = null) {
		$this->freepbx = $freepbx;
		$this->Backup = $freepbx->Backup;
		$this->webroot = $this->freepbx->Config->get('AMPWEBROOT');
		$this->backuptmpdir = $this->freepbx->Config->get('ASTSPOOLDIR').'/tmp';
		$this->transactionId = $transactionId;
		$this->restoreFile = $file;
		$this->pid = !empty($pid) ? $pid : posix_getpid();
	}


	public function process($warmspare = false) {
		if(!file_exists($this->restoreFile)) {
			throw new \Exception(sprintf(_('%s does not exist'),$this->restoreFile));
		}
		$errors = [];
		$warnings = [];

		$this->Backup->fs->remove($this->backuptmpdir);
		$this->Backup->fs->mkdir($this->backuptmpdir);

		$this->Backup->log($this->transactionId,_("Extracting Backup"));

		$tar = new Tar();
		$tar->open($this->restoreFile);
		$tar->extract($this->backuptmpdir);

		$metapath = $this->backuptmpdir . '/metadata.json';

		if(file_exists($metapath)){
			$restoreData = json_decode(file_get_contents($metapath), true);
		} else {
			$restoreData = [];
			$errors[] = _("Could not locate the manifest for this backup. This backup will not restore properly though the data may still be present.");
		}

		if(isset($restoreData['processorder'])){
			$this->restoreModules = $restoreData['processorder'];
		}
		if(!isset($restoreData['processorder'])){
			$this->restoreModules = $restoreData['modules'];
		}

		$backupModVer = (string)$this->freepbx->Modules->getInfo('backup')['backup']['version'];

		$this->Backup->log($this->transactionId,_("Running pre restore hooks"));
		$this->preHooks($restoreData, $errors);
		foreach($this->restoreModules as $key => $value) {
			$modjson = $this->backuptmpdir . '/modulejson/' . ucfirst($key) . '.json';
			if(!file_exists($modjson)){
				$msg = sprintf(_("Could not find a manifest for %s, skipping"),ucfirst($key));
				$this->Backup->log($this->transactionId,$msg,'WARNING');

				$errors[] = $msg;
				continue;
			}
			$moddata = json_decode(file_get_contents($modjson), true);
			$moddata['isWarmSpare'] = $warmspare;
			$restore = new Models\Restore($this->Backup->freepbx, $moddata, $backupModVer);
			$depsOk = $this->Backup->processDependencies($restore->getDependencies());
			if(!$depsOk){
				$errors[] = printf(_("Dependencies not resolved for %s Skipped"),$key);
				continue;
			}
			$modulehandler = new Handlers\FreePBXModule($this->freepbx);
			\modgettext::push_textdomain($key);
			$this->Backup->log($this->transactionId,sprintf(_("Running restore process for %s"),$key));
			$this->Backup->log($this->transactionId,sprintf(_("Resetting the data for %s, this may take a moment"),$key));
			try{
				$backedupVer = $value;
				$modulehandler->reset($key,$backedupVer);
				$this->Backup->log($this->transactionId,sprintf(_("Restoring the data for %s, this may take a moment"),$key));
				$class = sprintf('\\FreePBX\\modules\\%s\\Restore',ucfirst($key));
				$class = new $class($restore,$this->freepbx,$this->backuptmpdir);
				$class->runRestore($this->transactionId);
			} catch (Exception $e) {
				$this->Backup->log($this->transactionId, sprintf(_("There was an error running the restore for %s... %s"), $key, $e->getMessage()));
				if (DEBUG) {
					throw $e;
				}
			}

			\modgettext::pop_textdomain();
		}
		$this->Backup->log($this->transactionId,_("Running post restore hooks"));
		$this->postHooks($restoreData, $errors);
		$this->Backup->fs->remove($this->backuptmpdir);
		needreload();
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
		$amodules = $this->freepbx->Modules->getActiveModules();
		$validmods = [];
		foreach ($amodules as $module) {
			$bufile = $this->webroot . '/admin/modules/' . $module['rawname'].'/Restore.php';
			if(file_exists($bufile)){
				$validmods[] = $module;
			}
		}
		return $validmods;
	}
	public function preHooks($restoreData = [], &$errors){
		$err = [];
		$restoreData = base64_encode(json_encode($restoreData));
		$args = escapeshellarg($transactionId).' '.$restoreData;
		$this->freepbx->Hooks->processHooks($transactionId,$restoreData);
		$this->Backup->getHooks('restore');
		foreach($this->Backup->preRestore as $command){
			$cmd  = escapeshellcmd($command).' '.$args;
			exec($cmd,$out,$ret);
			if($ret !== 0){
				$errors[] = sprintf(_("%s finished with a non-zero status"),$cmd);
			}
		}
		unset($this->Backup->preRestore);
	}
	public function postHooks($restoreData=[], &$errors){
		$err = [];
		$restoreData = base64_encode(json_encode($restoreData));
		$args = escapeshellarg($transactionId).' '.$restoreData;
		$this->freepbx->Hooks->processHooks($transactionId);
		$this->Backup->getHooks('restore');
		foreach($this->Backup->postRestore as $command){
			$cmd  = escapeshellcmd($command).' '.$args;
			exec($cmd,$out,$ret);
			if($ret !== 0){
				$errors[] = sprintf(_("%s finished with a non-zero status"),$cmd);
			}
		}
		unset($this->Backup->postRestore);
	}
}
