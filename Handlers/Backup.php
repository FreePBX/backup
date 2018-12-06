<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 */
namespace FreePBX\modules\Backup\Handlers;
use FreePBX\modules\Backup\Handlers as Handlers;
use FreePBX\modules\Backup\Modules as Module;
use FreePBX\modules\Backup\Models as Models;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Helper\ProgressBar;
use splitbrain\PHPArchive\Tar;
class Backup{
	const DEBUG = false;
	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \InvalidArgumentException('Not given a BMO Object');
		}
		$this->FreePBX = $freepbx;
		$this->Backup = $freepbx->Backup;
	}

	/**
	 * Run the backup for the given id
	 * @param  string $id            Backup id
	 * @param  string $transactionId UUIDv4 string, if empty one will be generated
	 * @return mixed               true or array of errors
	 */
	public function process($id = '',$transactionId = '', $base64Backup = null, $pid = '') {
		if(empty($id) && empty($base64Backup)){
			throw new \Exception("Backup id not provided", 500);
		}
		$errors = [];
		$warnings = [];
		$this->Backup->delById('monolog');
		$handler = new Handlers\MonologKVStore($this->Backup);
		$this->Backup->logger->customLog->pushHandler($handler);
		$this->Backup->attachLoggers('backup');
		$pid = !empty($pid)?$pid:posix_getpid();
		$external = !empty($base64Backup);
		$transactionId = !empty($transactionId)?$transactionId:$this->Backup->generateId();
		$this->Backup->setConfig($transactionId,$pid,'running');
		$this->Backup->log($transactionId,sprintf(_("Running Backup ID: %s"),$id),'DEBUG');
		$this->Backup->log($transactionId,sprintf(_("Transaction: %s"),$transactionId),'DEBUG');
		$this->Backup->log($transactionId,_("Running pre backup hooks"));
		$this->preHooks($id, $transactionId);
		$base64Backup = !empty($base64Backup)?json_decode(base64_decode($base64Backup),true):false;
		$backupInfo = $external?$base64Backup:$this->Backup->getBackup($id);
		$this->Backup->attachEmail($backupInfo);
		$underscoreName = str_replace(' ', '_', $backupInfo['backup_name']);
		$this->Backup->log($transactionId,sprintf(_("Starting backup %s"),$underscoreName),'DEBUG');
		$spooldir = $this->FreePBX->Config->get("ASTSPOOLDIR");
		$serverName = str_replace(' ', '_',$this->FreePBX->Config->get('FREEPBX_SYSTEM_IDENT'));
		$localPath = sprintf('%s/backup/%s',$spooldir,$underscoreName);
		$this->Backup->fs->mkdir($localPath);
		$remotePath =  sprintf('/%s/%s',$serverName,$underscoreName);
		$tmpdir = sprintf('%s/backup/%s','/var/spool/asterisk/tmp',$underscoreName);
		@unlink($tmpdir);
		$this->Backup->fs->mkdir($tmpdir);
		//Use Legacy backup naming
		$tarfilename = sprintf('%s%s-%s-%s',date("Ymd-His-"),time(),get_framework_version(),rand());
		$tarnamebase = sprintf('%s/%s',$localPath,$tarfilename);
		$targzname = sprintf('%s.tar.gz',$tarnamebase);
		$this->Backup->log($transactionId,_("This backup will be stored locally is subject to maintenance settings"),'DEBUG');
		$this->Backup->log($transactionId,sprintf(_("Storage Location: %s"),$targzname));

		$tar = new Tar();
		$tar->create($targzname);

		$this->Backup->fs->mkdir($tmpdir . '/modulejson');
		$this->Backup->fs->mkdir($tmpdir . '/files');

		$tar->addFile($tmpdir . '/modulejson', 'modulejson');
		$tar->addFile($tmpdir . '/files', 'files');

		$storage_ids = $this->Backup->getStorageById($id);
		$this->dependencies = [];
		$processQueue = new \SplQueue();
		$processQueue->setIteratorMode(\SplQueue::IT_MODE_DELETE);
		$data = [];
		$dirs = [];
		$files = [];
		$cleanup = [];
		$manifest = [
			'modules' => [],
			'skipped' => [],
			'date' => time(),
			'backupInfo' => $backupInfo,
		];
		$validmods = $this->getModules();
		$backupItems = $this->Backup->getAll('modules_'.$id);
		if($external){
			$backupItems = $backupInfo['backup_items'];
		}
		$selectedmods = is_array($backupItems)?array_keys($backupItems):[];
		foreach($selectedmods as $mod) {
			$raw = \strtolower($mod);
			$this->sortDepends($raw,false,true);
			if(!in_array($mod, $validmods)){
				$err = sprintf(_("Could not backup module %s, it may not be installed or enabled"),$mod);
				$warnings[] = $err;
				$manifest['skipped'][] = $mod;
				$this->Backup->log($transactionId,$err,'DEBUG');
				continue;
			}
			$mod = $this->FreePBX->Modules->getInfo($raw);
			$processQueue->enqueue(['name' => $mod[$raw]['rawname']]);
		}

		if(!$external){
			$maint = new Module\Maintinance($this->FreePBX,$id);
		}
		if ($inCLI) {
			$cliout = new ConsoleOutput();
		}
		foreach($processQueue as $mod) {
			$backup = new Models\Backup($this->FreePBX);
			$backup->setBackupId($id);
			$mod = is_array($mod)?$mod['name']:$mod;
			$rawname = strtolower($mod);
			\modgettext::push_textdomain(strtolower($mod));
			$class = sprintf('\\FreePBX\\modules\\%s\\Backup', ucfirst($mod));
			if(!class_exists($class)){
				$err = sprintf(_("Couldn't find class %s"),$class);
				$this->Backup->log($transactionId,$err,'WARNING');
				continue;
			}
			try{
				$class = new $class($backup,$this->FreePBX);
				$class->runBackup($id,$transactionId);
			}catch(Exception $e){
				$this->Backup->log($transactionId, sprintf(_("There was an error running the backup for %s... %s"), $mod, $e->getMessage()));
				if(DEBUG){
					throw $e;
				}
			}
			\modgettext::pop_textdomain();
			$this->Backup->log($transactionId,sprintf(_("Processing backup for module: %s."), $mod));
			//Skip empty.
			if($backup->getModified() === false){
				$this->Backup->log($transactionId,sprintf(_("%s returned no data. This module may not implement the new backup yet. Skipping"), $mod));
				$manifest['skipped'][] = $mod;
				continue;
			}
			$dependencies = $backup->getDependencies();
			foreach($dependencies as $depend){
				/** If we are already backing up the module we don't need to put it in the queue
				 * If we haven't implimented backup it won't be there anyway
				 */
				if(in_array($depend, $selectedmods) || !in_array($depend, $validmods)){
					continue;
				}
				/** Add the dependency to the top of the lineup */
				if(in_array($depend, $validmods)){
					$raw = \strtolower($depend);
					$mod = $this->FreePBX->Modules->getInfo($raw);
					$this->sortDepends($mod[$raw]['rawname'],$mod[$raw]['version']);
					if(!empty($depend)){
						$processQueue->enqueue($depend);
					}
				}
			}
			$moduleinfo = $this->FreePBX->Modules->getInfo($rawname);
			$manifest['modules'][] = ['module' => $rawname, 'version' => $moduleinfo[$rawname]['version']];
			$moddata = $backup->getData();
			foreach ($moddata['dirs'] as $dir) {
				if(empty($dir)){
					continue;
				}
				$dirs[] = $this->Backup->getPath('files/' . ltrim($dir,'/'));
			}
			foreach ($moddata['files'] as $file) {
				$srcpath = isset($file['pathto'])?$file['pathto']:'';
				if (empty($srcpath)) {
					continue;
				}
				$srcfile = $srcpath .'/'. $file['filename'];
				$destpath = $this->Backup->getPath('files/' . ltrim($file['pathto'],'/'));
				$destfile = $destpath .'/'. $file['filename'];
				$dirs[] = $destpath;
				$files[$srcfile] = $destfile;
				$tar->addFile($srcfile,$destfile);
			}
			$mod = ucfirst($rawname);
			$modjson = $tmpdir . '/modulejson/' . $mod . '.json';
			if (!$this->Backup->fs->exists(dirname($modjson))) {
				$this->Backup->fs->mkdir(dirname($modjson));
			}
			file_put_contents($modjson, json_encode($moddata, JSON_PRETTY_PRINT));
			$tar->addFile($modjson,'modulejson/'.$mod.'.json');
			$data[$mod] = $moddata;
			$cleanup[$mod] = $moddata['garbage'];
		}

		foreach ($dirs as $dir) {
			$this->Backup->fs->mkdir($tmpdir . '/' . $dir);
			$tar->addFile($tmpdir . '/' . $dir, $dir);
		}
		$manifest['processorder'] = $this->dependencies;
		$tar->addData('metadata.json', json_encode($manifest));

		//Done with Tar, unlock the file so we can do stuff..
		unset($tar);
		if(!$external){
			$remote = $remotePath.'/'.$targzname;
			$this->Backup->log($transactionId,_("Saving to selected Filestore locations"));
			$hash = false;
			if(isset($signatures['hash'])){
				$hash = $signatures['hash'];
					$msg = sprintf(_("SHA256: %s"),$hash);
					$this->Backup->log($transactionId,$msg,'DEBUG');
			}
			foreach ($storage_ids as $location) {
				if(empty(trim($location))){
					continue;
				}
				try {
					$location = explode('_', $location);
					$this->Backup->FreePBX->Filestore->put($location[0],$location[1],file_get_contents($targzname),$remote);
					if($hash){
						$this->Backup->FreePBX->Filestore->put($location[0],$location[1],$hash,$remote.'.sha256sum');
					}
					$msg = sprintf(_("Saving to: %s instance"),$location[0]);
					$this->Backup->log($transactionId,$msg,'DEBUG');
				} catch (\Exception $e) {
					$err = $e->getMessage();
					$this->Backup->log($transactionId,$err,'ERROR');
					$errors[] = $err;
				}
			}
		}
		$this->Backup->log($transactionId,_("Cleaning up"));
		foreach ($cleanup as $key => $value) {
			$this->Backup->log($transactionId,sprintf(_("Cleaning up data generated by %s"),$key));
			$this->Backup->fs->remove($value);
		}

		if($external && empty($errors)){
			$this->Backup->fs->rename($targzname,getcwd().'/'.$transactionId.'.tar.gz');
			$this->Backup->log($transactionId,sprintf(_("Remote transaction complete, file saved to %s"),getcwd().'/'.$transactionId.'tar.gz'));
		}
		$this->Backup->fs->remove($tmpdir);

		if(!$external){
			$this->Backup->log($transactionId,_("Performing Local Maintnance"));
			$maint->processLocal();
			$this->Backup->log($transactionId,_("Performing Remote Maintnance"));
			$maint->processRemote();
		}
		$this->Backup->log($transactionId,_("Running post backup hooks"));
		$this->postHooks($id, $signatures, $errors, $transactionId);
		if(!empty($errors)){
			$this->Backup->errors = $errors;
			$this->Backup->log($transactionId,_("Backup finished with but with errors"),'WARNING');
			$this->Backup->processNotifications($id, $transactionId, $errors, $backupInfo['backup_name']);
			//TODO: Don't think I need this because monolog
			return $errors;
		}
		if(!empty($warnings)){
			$this->Backup->log($transactionId, _("Some warnings were logged. These are typically ok but should be reviewed"));
		}
		$this->Backup->log($transactionId,_("Backup completed successfully"));
		$this->Backup->processNotifications($id, $transactionId, [], $backupInfo['backup_name']);
		$this->Backup->setConfig('log',$this->sessionlog[$transactionId],$transactionId);
		$this->Backup->delConfig($transactionId,'running');
		return $signatures;
	}

	public function settingsMagic() {
		$settings = '';
		$mods = $this->FreePBX->Modules->getModulesByMethod("backupSettings");
		$mods = $this->getModules();
		foreach($mods as $mod) {
			\modgettext::push_textdomain(strtolower($mod));
			$settings .= $this->FreePBX->$mod->backupSettings();
			\modgettext::pop_textdomain();
		}
		return $settings;
	}

	public function processSettings($id,$settings){
		 $this->FreePBX->Hooks->processHooks($id,$settings);
	}
	public function getSettings($id){
		 return $this->FreePBX->Hooks->processHooks($id);
	}
	public function preHooks($id = '', $transactionId = ''){
		$err = [];
		$args = escapeshellarg($id).' '.escapeshellarg($transactionId);
		$this->FreePBX->Hooks->processHooks($id,$transactionId);
		$this->Backup->getHooks('backup');
		foreach($this->Backup->preBackup as $command){
			$cmd  = escapeshellcmd($command).' '.$args;
			exec($cmd,$out,$ret);
			if($ret !== 0){
				$errors[] = sprintf(_("%s finished with a non-zero status"),$cmd);
			}
		}
		unset($this->Backup->preBackup);
		return !empty($errors)?$errors:true;
	}
	public function postHooks($id = '', $signatures = [], $errors = [], $transactionId = ''){
		$err = [];
		$args = escapeshellarg($id).' '.escapeshellarg($transactionId).' '.base64_encode(json_encode($signatures,\JSON_PRETTY_PRINT)).' '.base64_encode(json_encode($errors,\JSON_PRETTY_PRINT));
		$this->FreePBX->Hooks->processHooks($id,$transactionId);
		$this->Backup->getHooks('backup');
		foreach($this->Backup->postBackup as $command){
			$cmd  = escapeshellcmd($command).' '.$args;
			exec($cmd,$out,$ret);
			if($ret !== 0){
				$errors[] = sprintf(_("%s finished with a non-zero status"),$cmd);
			}
		}
		unset($this->Backup->postBackup);
		return !empty($errors)?$errors:true;
	}
	

	/**
	 * Get a list of modules that implement the backup method
	 * @return array list of modules
	 */
	public function getModules($force = false){
		//Cache
		if(isset($this->backupMods) && !empty($this->backupMods) && !$force) {
			return $this->backupMods;
		}
		//All modules impliment the "backup" method so it is a horrible way to know
		//which modules are valid. With the autploader we can do this magic :)
		$webrootpath = $this->FreePBX->Config->get('AMPWEBROOT');
		$webrootpath = (isset($webrootpath) && !empty($webrootpath))?$webrootpath:'/var/www/html';
		$amodules = $this->FreePBX->Modules->getActiveModules();
		$validmods = [];
		foreach ($amodules as $module) {
			$bufile = $webrootpath . '/admin/modules/' . $module['rawname'].'/Backup.php';
			if(file_exists($bufile)){
				$validmods[] = ucfirst($module['rawname']);
			}
		}
		return $validmods;
	}
	public function sortDepends($dependency,$version = false,$skipsort = false){
		if(!$version){
			$moduleinfo = $this->FreePBX->Modules->getInfo($dependency);
			$version = $moduleinfo[$dependency]['version'];
		}
		$tmp[$dependency] = $version;
		foreach ($this->dependencies as $key => $value) {
			$tmp[$key] = $value;
		}
		$this->dependencies = $tmp;
		if(!$skipsort){
			$this->dependencies = array_unique($this->dependencies);
		}
	}
	
	static function parseFile($filename){
		//20171012-130011-1507838411-15.0.1alpha1-42886857.tar.gz
		preg_match("/(\d{7})-(\d{6})-(\d{10,11})-(.*)-\d*\.tar\.gz(.sha256sum)?/", $filename, $output_array);
		$valid = false;
		$arraySize = sizeof($output_array);
		if($arraySize == 5){
			$valid = true;
		}
		if($arraySize == 6){
			$valid = true;
		}
		if(!$valid){
			return false;
		}
		return [
			'filename' => $output_array[0],
			'datestring' => $output_array[1],
			'timestring' => $output_array[2],
			'timestamp' => $output_array[3],
			'framework' => $output_array[4],
			'isCheckSum' => ($arraySize == 6)
		];
	}
}
