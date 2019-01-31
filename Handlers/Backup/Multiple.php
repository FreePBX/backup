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
use Monolog\Handler\StreamHandler;
use Monolog\Formatter;
class Multiple extends Common {
	private $id;
	private $external = false;
	private $base64Backup;
	private $dependencies = [];

	/**
	 * Constructor
	 *
	 * @param FreePBX $freepbx
	 * @param string $id
	 * @param string $transactionId
	 * @param integer $pid
	 */
	public function __construct($freepbx, $id, $transactionId, $pid) {
		$filePath = sys_get_temp_dir().'/backup/'.$id.'/'.$transactionId;
		parent::__construct($freepbx, $filePath, $transactionId, $pid);
		$this->id = $id;
	}

	protected function setupLogger() {
		parent::setupLogger();
		$handler = new StreamHandler("php://stdout",\Monolog\Logger::DEBUG);
		$output = "%message%\n";
		$formatter = new Formatter\LineFormatter($output);
		$handler->setFormatter($formatter);
		$this->logger->pushHandler($handler);
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

		$this->log(sprintf(_("Running Backup ID: %s"),$this->id),'DEBUG');
		$this->log(sprintf(_("Transaction: %s"),$this->transactionId),'DEBUG');

		$this->log(_("Running pre backup hooks"));
		$this->preHooks($errors);
		$this->log(_("Finished running pre backup hooks"));

		$underscoreName = str_replace(' ', '_', $this->backupInfo['backup_name']);

		$this->Backup->attachEmail($this->backupInfo);

		$this->log(sprintf(_("Starting backup %s"),$underscoreName),'DEBUG');

		$localPath = sprintf('%s/backup/%s',$spooldir,$underscoreName);
		$this->fs->mkdir($localPath);

		$remotePath =  sprintf('/%s/%s',$serverName,$underscoreName);

		$tmpdir = sprintf('%s/backup/%s',$spooldir.'/tmp',$underscoreName);

		//reset tmp directories
		$this->fs->remove($tmpdir);
		$this->fs->mkdir($tmpdir);

		//Use Legacy backup naming
		$tarfilename = sprintf('%s%s-%s-%s',date("Ymd-His-"),time(),getVersion(),rand());
		$tarnamebase = sprintf('%s/%s',$localPath,$tarfilename);
		$targzname = sprintf('%s.tar.gz',$tarnamebase);
		$this->log(_("This backup will be stored locally and is subject to maintenance settings"),'DEBUG');
		$this->log(sprintf(_("Storage Location: %s"),$targzname));
		$this->setFilename($tarfilename);
		$this->openFile();

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
		$selectedmods = array_map('strtolower', $selectedmods);
		foreach($selectedmods as $mod) {
			if(!in_array($mod, $validmods)){
				$manifest['skipped'][] = ucfirst($mod);
				$this->log(sprintf(_("Could not backup module %s, it may not be installed or enabled"),$mod),'DEBUG');
				continue;
			}
			$this->sortDepends($raw,false,true);
			$processQueue->enqueue(['name' => $mod]);
		}

		//Process the Queue
		foreach($processQueue as $mod) {
			$moddata = $this->processModule($mod['name']);
			if(!empty($moddata['dependencies'])) {
				$moddeps = array_map('strtolower', $moddata['dependencies']);
				foreach($moddeps as $depend){
					if($depend === 'framework') {
						$this->log("\t".sprintf(_("Skpping %s which %s depends on because it is a system requirement"),$depend, $mod['name']),'DEBUG');
						continue;
					}
					if(!in_array($depend, $validmods)) {
						$manifest['skipped'][] = ucfirst($depend);
						$this->log("\t".sprintf(_("Could not backup module %s which %s depends on, it may not be installed or enabled"),$depend, $mod['name']),'DEBUG');
						continue;
					}
					//If we are already backing up the module we don't need to put it in the queue If we haven't implimented backup it won't be there anyway
					if(in_array($depend, $selectedmods)){
						continue;
					}
					// Add the dependency to the top of the lineup
					if(in_array($depend, $validmods)){
						$this->log("\t".sprintf(_("Adding module %s which %s depends on"),$depend, $mod['name']),'DEBUG');
						$mod = $this->freepbx->Modules->getInfo($depend);
						$this->sortDepends($mod[$depend]['rawname'],$mod[$depend]['version']);
						$selectedmods[] = $depend;
						$processQueue->enqueue(['name' => $mod[$depend]['rawname']]);
					}
				}
			}
			/*
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
			*/
		}

		echo "end";

		/*
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
		*/
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
				$validmods[] = $module['rawname'];
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
