<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 */
namespace FreePBX\modules\Backup\Handlers\Backup;
use FreePBX\modules\Backup\Modules as Module;
use FreePBX\modules\Backup\Models as Models;
use splitbrain\PHPArchive\Tar;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use function FreePBX\modules\Backup\Json\json_decode;
use function FreePBX\modules\Backup\Json\json_encode;
class Multiple extends Common {

	private $dependencies = [];
	private $freepbx;
	private $Backup;
	private $id;
	private $transactionId;
	private $external = false;
	private $base64Backup;

	/**
	 * Constructor
	 *
	 * @param FreePBX $freepbx
	 * @param string $id
	 * @param string $transactionId
	 * @param integer $pid
	 */
	public function __construct($freepbx, $id, $transactionId, $pid = null) {
		$this->freepbx = $freepbx;
		$this->Backup = $freepbx->Backup;
		$this->pid = !empty($pid) ? $pid : posix_getpid();
		$this->id = $id;
		$this->transactionId = $transactionId;
	}

	public function __get($var) {
		switch($var) {
			case 'backupInfo':
				return !$this->external ? $this->Backup->getBackup($this->id) : json_decode(base64_decode($this->base64Backup),true);
			break;
		}
	}

	/**
	 * Set this to a Base64 Backup
	 */
	public function setBase64Backup($base64) {
		$this->base64Backup = $base64;
		$this->external = true;
	}

	/**
	 * Process the backup
	 *
	 * @return void
	 */
	public function process() {
		if(empty($this->id)){
			throw new \Exception("Backup id not provided", 500);
		}
		$errors = [];
		$warnings = [];
		$spooldir = $this->freepbx->Config->get("ASTSPOOLDIR");
		$serverName = str_replace(' ', '_',$this->freepbx->Config->get('FREEPBX_SYSTEM_IDENT'));

		$this->Backup->log($this->transactionId,sprintf(_("Running Backup ID: %s"),$this->id),'DEBUG');
		$this->Backup->log($this->transactionId,sprintf(_("Transaction: %s"),$this->transactionId),'DEBUG');

		$this->Backup->log($this->transactionId,_("Running pre backup hooks"));
		$this->preHooks($errors);
		$this->Backup->log($this->transactionId,_("Finished running pre backup hooks"));

		$underscoreName = str_replace(' ', '_', $this->backupInfo['backup_name']);

		$this->Backup->attachEmail($this->backupInfo);

		$this->Backup->log($this->transactionId,sprintf(_("Starting backup %s"),$underscoreName),'DEBUG');

		$localPath = sprintf('%s/backup/%s',$spooldir,$underscoreName);
		$this->Backup->fs->mkdir($localPath);

		$remotePath =  sprintf('/%s/%s',$serverName,$underscoreName);

		$tmpdir = sprintf('%s/backup/%s',$spooldir.'/tmp',$underscoreName);
		//reset tmp directories
		$this->Backup->fs->remove($tmpdir);
		$this->Backup->fs->mkdir($tmpdir);

		//Use Legacy backup naming
		$tarfilename = sprintf('%s%s-%s-%s',date("Ymd-His-"),time(),getVersion(),rand());
		$tarnamebase = sprintf('%s/%s',$localPath,$tarfilename);
		$targzname = sprintf('%s.tar.gz',$tarnamebase);
		$this->Backup->log($this->transactionId,_("This backup will be stored locally and is subject to maintenance settings"),'DEBUG');
		$this->Backup->log($this->transactionId,sprintf(_("Storage Location: %s"),$targzname));

		//Open the tarball
		$tar = new Tar();
		$tar->setCompression(9, Tar::COMPRESS_GZIP);
		$tar->create($targzname);
		$this->Backup->fs->mkdir($tmpdir . '/modulejson');
		$this->Backup->fs->mkdir($tmpdir . '/files');

		//add diles
		$tar->addFile($tmpdir . '/modulejson', 'modulejson');
		$tar->addFile($tmpdir . '/files', 'files');

		//Setup the process queue
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
			'backupInfo' => $this->backupInfo,
		];
		$validmods = $this->getModules();
		$backupItems = $this->Backup->getAll('modules_'.$this->id);
		if($this->external){
			$backupItems = $this->backupInfo['backup_items'];
		}
		$selectedmods = is_array($backupItems)?array_keys($backupItems):[];
		foreach($selectedmods as $mod) {
			$raw = \strtolower($mod);
			$this->sortDepends($raw,false,true);
			if(!in_array($mod, $validmods)){
				$err = sprintf(_("Could not backup module %s, it may not be installed or enabled"),$mod);
				$warnings[] = $err;
				$manifest['skipped'][] = $mod;
				$this->Backup->log($this->transactionId,$err,'DEBUG');
				continue;
			}
			$mod = $this->freepbx->Modules->getInfo($raw);
			$processQueue->enqueue(['name' => $mod[$raw]['rawname']]);
		}

		if(!$this->external){
			$maint = new Module\Maintenance($this->freepbx,$this->id);
		}

		//Process the Queue
		foreach($processQueue as $mod) {
			$backup = new Models\Backup($this->freepbx);
			$backup->setBackupId($this->id);
			$mod = is_array($mod)?$mod['name']:$mod;
			$rawname = strtolower($mod);
			\modgettext::push_textdomain(strtolower($mod));
			$class = sprintf('\\FreePBX\\modules\\%s\\Backup', ucfirst($mod));
			if(!class_exists($class)){
				$err = sprintf(_("Couldn't find class %s"),$class);
				$this->Backup->log($this->transactionId,$err,'WARNING');
				continue;
			}
			try{
				$class = new $class($backup,$this->freepbx);
				$class->runBackup($this->id,$this->transactionId);
			}catch(Exception $e){
				$this->Backup->log($this->transactionId, sprintf(_("There was an error running the backup for %s... %s"), $mod, $e->getMessage()));
			}
			\modgettext::pop_textdomain();
			$this->Backup->log($this->transactionId,sprintf(_("Processing backup for module: %s."), $mod));
			//Skip empty.
			if($backup->getModified() === false){
				$this->Backup->log($this->transactionId,sprintf(_("%s returned no data. This module may not implement the new backup yet. Skipping"), $mod));
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
					$mod = $this->freepbx->Modules->getInfo($raw);
					$this->sortDepends($mod[$raw]['rawname'],$mod[$raw]['version']);
					if(!empty($depend)){
						$processQueue->enqueue($depend);
					}
				}
			}
			$moduleinfo = $this->freepbx->Modules->getInfo($rawname);
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
		$tar->addData('metadata.json', json_encode($manifest,JSON_PRETTY_PRINT));
		$tar->close();

		//get Storage location
		$storage_ids = $this->Backup->getStorageById($this->id);

		if(!$this->external){
			$remote = $remotePath.'/'.$targzname;
			$this->Backup->log($this->transactionId,_("Saving to selected Filestore locations"));
			$hash = false;
			if(isset($signatures['hash'])){
				$hash = $signatures['hash'];
					$msg = sprintf(_("SHA256: %s"),$hash);
					$this->Backup->log($this->transactionId,$msg,'DEBUG');
			}
			foreach ($storage_ids as $location) {
				if(empty(trim($location))){
					continue;
				}
				try {
					$location = explode('_', $location);
					$this->Backup->freepbx->Filestore->put($location[0],$location[1],file_get_contents($targzname),$remote);
					if($hash){
						$this->Backup->freepbx->Filestore->put($location[0],$location[1],$hash,$remote.'.sha256sum');
					}
					$msg = sprintf(_("Saving to: %s instance"),$location[0]);
					$this->Backup->log($this->transactionId,$msg,'DEBUG');
				} catch (\Exception $e) {
					$err = $e->getMessage();
					$this->Backup->log($this->transactionId,$err,'ERROR');
					$errors[] = $err;
				}
			}
		}
		$this->Backup->log($this->transactionId,_("Cleaning up"));
		foreach ($cleanup as $key => $value) {
			$this->Backup->log($this->transactionId,sprintf(_("Cleaning up data generated by %s"),$key));
			$this->Backup->fs->remove($value);
		}

		if($this->external && empty($errors)){
			$this->Backup->fs->rename($targzname,getcwd().'/'.$this->transactionId.'.tar.gz');
			$this->Backup->log($this->transactionId,sprintf(_("Remote transaction complete, file saved to %s"),getcwd().'/'.$this->transactionId.'tar.gz'));
		}
		$this->Backup->fs->remove($tmpdir);

		if(!$this->external){
			$this->Backup->log($this->transactionId,_("Performing Local Maintnance"));
			$maint->processLocal();
			$this->Backup->log($this->transactionId,_("Performing Remote Maintnance"));
			$maint->processRemote();
		}
		$this->Backup->log($this->transactionId,_("Running post backup hooks"));
		$this->postHooks($signatures, $errors);
		if(!empty($errors)){
			$this->Backup->errors = $errors;
			$this->Backup->log($this->transactionId,_("Backup finished with but with errors"),'WARNING');
			$this->Backup->processNotifications($this->id, $this->transactionId, $errors, $this->backupInfo['backup_name']);
			//TODO: Don't think I need this because monolog
			return $errors;
		}
		if(!empty($warnings)){
			$this->Backup->log($this->transactionId, _("Some warnings were logged. These are typically ok but should be reviewed"));
		}
		$this->Backup->log($this->transactionId,_("Backup completed successfully"));
		$this->Backup->processNotifications($this->id, $this->transactionId, [], $this->backupInfo['backup_name']);
		return $signatures;
	}

	/**
	 * Pre Backup Hook Scripts
	 *
	 * @return void
	 */
	private function preHooks(&$errors = []){
		$this->freepbx->Hooks->processHooks($this->id,$this->transactionId);
		$this->Backup->getHooks('backup');
		foreach($this->Backup->preBackup as $command){
			$process = new Process([$command, $this->id, $this->transactionId]);
			$process->run();
			if (!$process->isSuccessful()) {
				$errors[] = sprintf(_("%s finished with a non-zero status"),$cmd);
			}
		}
		unset($this->Backup->preBackup);
	}

	/**
	 * Post Backup Hooks Scripts
	 *
	 * @param array $signatures
	 * @param array $errors
	 * @return void
	 */
	private function postHooks($signatures = [], &$errors = []){
		$this->freepbx->Hooks->processHooks($this->id,$this->transactionId);
		$this->Backup->getHooks('backup');
		foreach($this->Backup->postBackup as $command){
			$process = new Process([$command, $this->id, $this->transactionId, base64_encode(json_encode($signatures,\JSON_PRETTY_PRINT)), base64_encode(json_encode($errors,\JSON_PRETTY_PRINT))]);
			$process->run();
			if (!$process->isSuccessful()) {
				$errors[] = sprintf(_("%s finished with a non-zero status"),$cmd);
			}
		}
		unset($this->Backup->postBackup);
	}

	/**
	 * Get a list of modules that implement the backup method
	 * @return array list of modules
	 */
	private function getModules($force = false){
		//Cache
		if(isset($this->backupMods) && !empty($this->backupMods) && !$force) {
			return $this->backupMods;
		}
		//All modules impliment the "backup" method so it is a horrible way to know
		//which modules are valid. With the autploader we can do this magic :)
		$webrootpath = $this->freepbx->Config->get('AMPWEBROOT');
		$amodules = $this->freepbx->Modules->getActiveModules();
		$validmods = [];
		foreach ($amodules as $module) {
			$bufile = $webrootpath . '/admin/modules/' . $module['rawname'].'/Backup.php';
			if(file_exists($bufile)){
				$validmods[] = ucfirst($module['rawname']);
			}
		}
		return $validmods;
	}

	/**
	 * Sort Dependencies
	 *
	 * @param string $dependency
	 * @param boolean $version
	 * @param boolean $skipsort
	 * @return void
	 */
	private function sortDepends($dependency,$version = false,$skipsort = false){
		if(!$version){
			$moduleinfo = $this->freepbx->Modules->getInfo($dependency);
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
}
