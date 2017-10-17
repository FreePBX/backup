<?php
/**
 * Copyright Sangoma Technologies, Inc 2015
 */
namespace FreePBX\modules;
use FreePBX\modules\Backup\Handlers as Handler;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\LockHandler;


class Backup extends \DB_Helper implements \BMO {
	public function __construct($freepbx = null) {
		if ($freepbx == null) {
				throw new Exception('Not given a FreePBX Object');
		}
		$this->FreePBX = $freepbx;
		$this->db = $freepbx->Database;
		$this->fs = new Filesystem;
		$this->backupFields = ['backup_name','backup_description','backup_items','backup_storage','backup_schedule','schedule_enabled','maintage','maintruns','backup_email','backup_emailtype','immortal'];
		$this->templateFields = [];
		$this->serverName = $this->FreePBX->Config->get('FREEPBX_SYSTEM_IDENT');
		$this->sessionlog = [];
	}
	//BMO STUFF
	public function install(){
		//Filestore
		$this->migrateStorage();
		//DB Manager

		//Migrate Backup data
		$this->migrateBackupJobs();
		//If anyone is listening they can attempt a data migration.
		$this->FreePBX->Hooks->processHooks($this);
		$this->setConfig('warmspare', true);
	}

	public function uninstall(){
	}

	public function backup(){
	}

	public function restore($backup){
	}

	public function doConfigPageInit($page) {
		switch ($page) {
			case 'backup':
				if(isset($_REQUEST['action']) && $_REQUEST['action'] == 'delete'){
					return $this->deleteBackup($_REQUEST['id']);
				}
				if(isset($_POST['backup_name'])){
					return $this->updateBackup($_POST);
				}
			break;
			default:
			break;
		}
	}

	/**
	 * Action bar in 13+
	 * @param [type] $request [description]
	 */
	public function getActionBar($request) {
		$buttons = array(
			'reset' => array(
				'name' => 'reset',
				'id' => 'reset',
				'value' => _('Reset'),
			),
			'submit' => array(
				'name' => 'submit',
				'id' => 'submit',
				'value' => _('Save'),
			),
			'run' => array(
				'name' => 'run',
				'id' => 'run_backup',
				'value' => _('Save and Run'),
			),
			'delete' => array(
				'name' => 'delete',
				'id' => 'delete',
				'value' => _('Delete'),
			),
		);
		switch ($request['display']) {
			case 'backup':
			break;
			case 'backup_restore':
			case 'backup_templates':
				unset($buttons['run']);
			break;
			default:
				$buttons = [];
			break;
		}
		if(!isset($request['id']) || empty($request['id'])){
			unset($buttons['delete']);
			unset($buttons['run']);
		}
		if(!isset($request['view']) || empty($request['view'])){
			$buttons = [];
		}
		return $buttons;
	}

	/**
	 * Ajax Request for BMO
	 * @param string $req     [description]
	 * @param [type] $setting [description]
	 */
	public function ajaxRequest($req, &$setting) {
		switch ($req) {
			case 'getJSON':
			case 'run':
			case 'runstatus':
			case 'getlog':
			case 'restoreFiles':
			case 'uploadrestore':
				return true;
			break;
			default:
				return false;
			break;
		}
	}

	/**
	 * Ajax Handler for BMO
	 */
	public function ajaxHandler() {
		switch ($_REQUEST['command']) {
			case 'uploadrestore':
			$id = $this->generateId();
			if(!isset($_FILES['filetorestore'])){
				return ['status' => false, 'error' => _("No file provided")];
			}
			if($_FILES['filetorestore']['error'] !== 0){
				return ['status' => false, 'err' => $_FILES['filetorestore']['error'], 'message' => _("File reached the server but could not be processed")];
			}
			if($_FILES['filetorestore']['type'] != 'application/x-gzip'){
				return ['status' => false, 'mime' => $_FILES['filetorestore']['type'], 'message' => _("The uploaded file type is incorrect and couldn't be processed")];
			}
			$spooldir = $this->FreePBX->Config->get("ASTSPOOLDIR");
			$path = sprintf('%s/backup/uploads/',$spooldir);
			//This will ignore if exists
			$this->fs->mkdir($path);
			$file = $path.basename($_FILES['filetorestore']['name']);
			if (!move_uploaded_file($_FILES['filetorestore']['tmp_name'],$file)){
				return ['status' => false, 'message' => _("Failed to copy the uploaded file")];
			}
			$this->setConfig('file', $file, $id);
			$backupphar = new \PharData($file);
			$meta = $backupphar->getMetadata();
			$this->setConfig('meta', $meta, $id);
			return ['status' => true, 'id' => $id, 'meta' => $meta];
			case 'restoreFiles':
				return [];
			case 'run':
				if(!isset($_GET['id'])){
					return ['status' => false, 'message' => _("No backup id provided")];
				}
				$buid = escapeshellarg($_GET['id']);
				$jobid = $this->generateId();
				$process = new Process('fwconsole backup --backup='.$buid.' --transaction='.$jobid);
				//$process->disableOutput();
				try {
					$process->mustRun();
				} catch (\Exception $e) {
					dbug($process->getOutput());
					dbug($process->getErrorOutput());
					return ['status' => false, 'message' => _("Couldn't run process.")];
				}

				$pid = $process->getPid();
				return ['status' => true, 'message' => _("Backup running"), 'process' => $pid, 'transaction' => $jobid, 'backupid' => $buid];
			case 'runstatus':
				if(!isset($_GET['id']) || !isset($_GET['transaction'])){
					return ['status' => 'stopped', 'error' => _("Missing id or transaction")];
				}
				$job = $_GET['transaction'];
				$buid = $_GET['id'];
				$lockHandler = new LockHandler($job.'.'.$buid);
				if (!$lockHandler->lock()) {
					$lockHandler->release();
					return ['status' => 'running'];
				}
				return ['status' => 'stopped'];
			case 'getLog':
				if(!isset($_GET['transaction'])){
					return[];
				}
				$ret = $this->getAll($_GET['transaction']);
				return $ret?$ret:[];
			case 'getJSON':
				switch ($_REQUEST['jdata']) {
					case 'backupGrid':
						return array_values($this->listBackups());
					break;
					case 'templateGrid':
						return [];
						//return array_values($this->listTemplates());
					break;
					case 'backupStorage':
						$storage_ids = [];
						if(isset($_GET['id']) && !empty($_GET['id'])){
							$storage_ids = $this->getStorageByID($_GET['id']);
						}
						try {
							$fstype = $this->getFSType();
							$items = $this->FreePBX->Filestore->listLocations($fstype);
							$return = [];
							foreach ($items['locations'] as $driver => $locations ) {
								$optgroup = [
									'label' => $driver,
									'children' => []
								];
								foreach ($locations as $location) {
									$select = in_array($driver.'_'.$location['id'], $storage_ids);
									$optgroup['children'][] = [
										'label' => $location['name'],
										'title' => $location['description'],
										'value' => $driver.'_'.$location['id'],
										'selected' => $select
									];
								}
								$return[] = $optgroup;
							}
							return $return;
						} catch (\Exception $e) {
							return $e;
						}
					break;
					case 'backupItems':
					$id = isset($_GET['id'])?$_GET['id']:'';
					return $this->getBackupModulesById($id);
					break;
					default:
						return false;
					break;
				}
			break;
			default:
				return false;
			break;
		}
	}
	//TODO: This whole thing
	public function getRightNav($request) {
		//We don't need an rnav if the view is not set
		if(isset($_GET['display']) && isset($_GET['view'])){
			switch ($_GET['display']) {
				case 'backup':
				case 'backup_templates':
				case 'backup_restore':
					return "Placeholder";
				break;
			}
		}
	}

	//Display stuff
	public function showPage($page){
		switch ($page) {
			case 'backup':
				if(isset($_GET['view']) && $_GET['view'] == 'form'){
					$randcron = sprintf('59 23 * * %s',rand(0,6));
					$vars = ['id' => ''];
					$vars['backup_schedule'] = $randcron;
					if(isset($_GET['id']) && !empty($_GET['id'])){
						$vars = $this->getBackup($_GET['id']);
						$vars['backup_schedule'] = !empty($vars['backup_schedule'])?$vars['backup_schedule']:$randcron;
						$vars['id'] = $_GET['id'];
					}
					$vars['warmspare'] = '';
					if($this->getConfig('warmspare')){
						$vars['warmspare'] = load_view(__DIR__.'/views/backup/warmspare.php',$vars);
					}
					return load_view(__DIR__.'/views/backup/form.php',$vars);
				}
				if(isset($_GET['view']) && $_GET['view'] == 'download'){
					return load_view(__DIR__.'/views/backup/download.php');
				}
				return load_view(__DIR__.'/views/backup/grid.php');
			break;
			case 'restore':
				$view = isset($_GET['view'])?$_GET['view']:'default';
				switch ($view) {
					case 'processrestore':
						if(!isset($_GET['id']) || empty($_GET['id'])){
							return load_view(__DIR__.'/views/restore/landing.php',['error' => _("No id was specified to process. Please try submitting your file again.")]);
						}
						$backupjson = [];
						$vars = $this->getAll($_GET['id']);
						$vars['missing'] = "yes";
						$vars['reset'] = "no";
						$vars['enabletrunks'] = "yes";
						foreach ($vars['meta']['modules'] as $module) {
							$mod = strtolower($module);
							$status = $this->FreePBX->Modules->checkStatus($mod);
							$backupjson[] = [
								'modulename' => $module,
								'installed' => $status
							];
						}
						$vars['jsondata'] = json_encode($backupjson);
						return load_view(__DIR__.'/views/restore/processRestore.php',$vars);
					break;
					default:
						return load_view(__DIR__.'/views/restore/landing.php');
					break;
				}
			break;
		}
	}

	public function getBackupSettingsDisplay($module,$id = ''){
		$hooks = $this->FreePBX->Hooks->processHooks($module,$id);
		if(empty($hooks)){
			return false;
		}
		$ret = '<div class="hooksetting">';
		foreach ($hooks as $key => $value) {
			$ret .= $value;
		}
		$ret .= '</div>';
		return $ret;
	}

	/** Oh... Migration, migration, let's learn about migration. It's nature's inspiration to move around the sea.
	* We have split the functionality up so things backup use to do may be done by another module. The other module(s)
	* May not yet be installed or may install after.  So we need to keep a kvstore with the various data and when installing
	* The other modules will checkin on install and process the data needed by them.
	**/

	//on install if the module/method is not yet a thing we will store it's data here
	public function migrateBackupJobs(){
		//['backup_name','backup_description','backup_items','backup_storage','backup_schedule','maintage','maintruns','backup_email','backup_emailtype','immortal'];
		$ids = array();
		try {
			$q = $this->db->query('SELECT * FROM backup');
			while ($item = $q->fetch(\PDO::FETCH_ASSOC)) {
				$id = $this->generateId();
				$insert = ['id' => $id];
				if($this->getConfig($item['id'],'migratedbackups')){
					continue;
				}
				$default = sprintf("Migrated Backup id: %s",$item['id']);
				$insert['backup_name'] = isset($item['name'])?$item['name']:$default;
				$insert['backup_description'] = isset($item['description'])?$item['description']:$default;
				$insert['backup_email'] = isset($item['email'])?$item['email']:$default;
				$immortal = (isset($item['immortal']) && !is_null($item['immortal']));
				$this->updateBackup($insert);
				$this->updateBackupSetting($id, 'immortal', $immortal);
				$this->updateBackupSetting($id, 'migratedid', $item['id']);
				$ids[] = ['oldid' => $item['id'], 'newid' => $id];
			}
		} catch (\Exception $e) {
			if($e->getCode() != '42S02'){
				throw $e;
			}
		}
		$stmt = $this->db->prepare('SELECT `key`,`value` FROM backup_details WHERE backup_id = :id');
		foreach ($ids as $item) {
			try {
				$stmt->execute([':id' => $item['oldid']]);
				$data = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
				if(isset($data['emailfailonly']) && !empty($data['emailfailonly'])){
					$this->updateBackupSetting($item['newid'], 'backup_emailtype', 'failure');
				}
				if(isset($data['delete_ammount']) && !empty($data['delete_ammount'])){
					$this->updateBackupSetting($item['newid'], 'maintruns', $data['delete_ammount']);
				}
			} catch (\Exception $e) {
				if($e->getCode() != '42S02'){
					throw $e;
				}
			}

			$this->setConfig($item['oldid'],$item['newid'],'migratedbackups');

		}
	}
	public function migrateStorage(){
		if($this->getMigrationFlag('filestore')){
			return true;
		}
		$stmt = $this->db->query('SELECT `server_id`,`key`,`value` FROM backup_server_details');
		$servers = array();
		while($item = $stmt->fetch(\PDO::FETCH_ASSOC)){
		 $servers[$item['server_id']][$item['key']] = $item['value'];
		}
		$stmt = $this->db->query('SELECT * FROM backup_servers');
		while($item = $stmt->fetch(\PDO::FETCH_ASSOC)){
		 $servers[$item['id']]['name'] = $item['name'];
		 $servers[$item['id']]['description'] = $item['desc'];
		 $servers[$item['id']]['type'] = $item['type'];
		 $servers[$item['id']]['immortal'] = !is_null($item['immortal']);
		 $servers[$item['id']]['readonly'] = !is_null($item['readonly']);
		}
		$this->setMigration('filestore', $servers);
	}
	public function setMigration($rawname,$data){
		$this->setConfig('data',$data,$rawname);
	}
	//Set a flag that says we have migrated
	public function setMigtatedFlag($rawname){
		$this->setConfig('migrated',true,$rawname);
	}
	//Check if we have migrated
	public function getMigrationFlag($rawname){
		return $this->getConfig('migrated',$rawname);
	}
	//Get any migration data the module has for us
	public function getMigration($rawname){
		return $this->getConfig('data',$rawname);
	}
	public function cleanMigrationData($rawname){
		$this->setConfig('data',false,$rawname);
	}
	//End migration stuff

	//Getters

	/**
	 * Get storage locations by backup ID
	 * @param  string $id backup id
	 * @return array  array of backup locations as DRIVER_ID
	 */
	public function getStorageById($id){
		$storage = $this->getConfig('backup_storage',$id);
		return is_array($storage)?$storage:[];
	}

	/**
	 * Gets the appropriate filesystem types to pass to filestore.
	 * @return mixed if hooks are present it will present an array, otherwise a string
	 */
	public function getFSType(){
		$types = $this->FreePBX->Hooks->processHooks();
		$ret=[];
		foreach ($types as $key => $value) {
			$value = is_array($value)?$value:[];
			$ret = array_merge($ret,$value);
		}
		return !empty($ret)?$ret:'backup';
	}

	/**
	 * List all backups
	 * @return array Array of backup items
	 */
	public function listBackups() {
		$return =  $this->getAll('backupList');
		return is_array($return)?$return:[];
	}

	/**
	 * Get all settings for a specific backup id
	 * @param  string $id backup id
	 * @return array  an array of backup settings
	 */
	public function getBackup($id){
		$data = $this->getAll($id);
		$return = [];
		foreach ($this->backupFields as $key) {
			switch ($key) {
				default:
					$return[$key] = isset($data[$key])?$data[$key]:'';
				break;
			}
		}
		return $return;
	}

	/**
	 * Get a list of modules that implement the backup method
	 * @return array list of modules
	 */
	public function getBackupModules(){
		return $this->FreePBX->Modules->getModulesByMethod("backup");
	}

	/**
	 * Get a list of modules that implement the restore method
	 * @return array list of modules
	 */
	public function getRestoreModules(){
		return $this->FreePBX->Modules->getModulesByMethod("restore");
	}

	/**
	 * Get modules for a specific backup id returned in an array
	 * @param  string  $id              The backup id
	 * @param  boolean $selectedOnly    Only return the modules selected
	 * @param  boolean $includeSettings Include settings html for rendering in the UI
	 * @return array   list of module data
	 */
	public function getBackupModulesById($id = '',$selectedOnly = false, $includeSettings = true){
		$modules = $this->getBackupModules();
		if(empty($id)){
			return $modules;
		}
		$selected = $this->getAll('modules_'.$id);
		$selected = is_array($selected)?array_keys($selected):[];
		if($selectedOnly){
			return $selected;
		}
		$ret = [];
		foreach ($modules as $module) {
			$item = [
				'modulename' => $module,
				'selected' => in_array($module, $selected),
			];
			if($includeSettings){
				$item['settingdisplay'] = $this->getBackupSettingsDisplay($module, $id);
			}
			$ret[] = $item;
		}
		return $ret;
	}


	//Setters
	public function scheduleJobs($id = 'all'){
		if($id !== 'all'){
			$enabled = $this->getBackupSetting($id, 'schedule_enabled');
			if($enabled === 'yes'){
				$schedule = $this->getBackupSetting($id, 'backup_schedule');
				$command = sprintf('/usr/sbin/fwconsole backup backup=%s > /dev/null 2>&1',$id);
				$this->FreePBX->Cron->removeAll($command);
				$this->FreePBX->Cron->add($schedule.' '.$command);
				return true;
			}
		}
		//Clean slate
		$allcrons = $this->FreePBX->Cron->getAll();
		$allcrons = is_array($allcrons)?$allcrons:[];
		foreach ($allcrons as $string => $cmd) {
			if (strpos($cmd, 'fwconsole backup') !== false) {
				$this->FreePBX->Cron->remove($cmd);
			}
		}
		$backups = $this->listBackups();
		foreach ($backups as $key => $value) {
			$enabled = $this->getBackupSetting($key, 'schedule_enabled');
			if($enabled === 'yes'){
				$schedule = $this->getBackupSetting($key, 'backup_schedule');
				$command = sprintf('/usr/sbin/fwconsole backup backup=%s > /dev/null 2>&1',$key);
				$this->FreePBX->Cron->removeAll($command);
				$this->FreePBX->Cron->add($schedule.' '.$command);
			}
		}
		return true;
	}
	/**
	 * Update/Add a backup item. Note the only difference is weather we generate an ID
	 * @param  array $data an array of the items needed. typically just send the $_POST array
	 * @return string the backup id
	 */
	public function updateBackup($data){
		$data['id'] = (isset($data['id']) && !empty($data[id]))?$data['id']:$this->generateID();
		foreach ($this->backupFields as $col) {
			//This will be set independently
			if($col == 'immortal'){
				continue;
			}
			$value = isset($data[$col])?$data[$col]:'';
			$this->updateBackupSetting($data['id'], $col, $value);
		}
		$description = isset($data['backup_description'])?$data['backup_description']:sprintf('Backup %s',$data['backup_name']);
		$this->setConfig($data['id'],array('id' => $data['id'], 'name' => $data['backup_name'], 'description' => $description),'backupList');
		if(isset($data['backup_items']) && $data['backup_items'] !== 'unchanged'){
			$backup_items = json_decode($data['backup_items'],true);
			$backup_items = is_array($backup_items)?$backup_items:[];
			$this->setModulesById($data['id'], $backup_items);
		}
		if(isset($data['backup_items_settings']) && $data['backup_items_settings'] !== 'unchanged' ){
			$this->processBackupSettings($data['id'], json_decode($data['backup_items_settings'],true));
		}
		$this->scheduleJobs($id);
		return $id;
	}

	public function updateBackupSetting($id, $setting, $value=false){
		$ret = $this->setConfig($setting,$value,$id);
		if($setting == 'backup_schedule'){
			$this->scheduleJobs($id);
		}
	}
	public function getBackupSetting($id,$setting){
		return $this->getConfig($setting, $id);
	}
	/**
	 * delete backup by ID
	 * @param  string $id backup id
	 * @return bool	success/failure
	 */
	public function deleteBackup($id){
		$this->setConfig($id,false,'backupList');
		$this->delById($id);
		//This should return an empty array if successful.
		$this->scheduleJobs('all');
		return empty($this->getBackup($id));
	}

	/**
	 * Set the modules to backup for a specific id. This nukes prior data
	 * @param string $id      backup id
	 * @param array $modules associative array of modules [['modulename' => 'foo'], ['modulename' => 'bar']]
	 */
	public function setModulesById($id,$modules){
		$this->delById('modules_'.$id);
		foreach ($modules as $module) {
			if(!isset($module['modulename'])){
				continue;
			}
			$this->setConfig($module['modulename'],true,'modules_'.$id);
		}
		return $this->getAll('modules_'.$id);
	}

	//Workers

	/**
	 * Run the backup for the given id
	 * @param  string $id            Backup id
	 * @param  string $transactionId UUIDv4 string, if empty one will be generated
	 * @return mixed               true or array of errors
	 */
	public function doBackup($id = '',$transactionId = '', $base64Backup = null) {
		if(empty($id) && empty($base64Backup)){
			throw new \Exception("Backup id not provided", 500);
		}
		$external = !empty($base64Backup);
		$transactionId = !empty($transactionId)?$transactionId:$this->generateId();
		$this->log($transactionId,_("Running pre backup hooks"));
		$this->preBackupHooks($id, $transactionId);
		$base64Backup = !empty($base64Backup)?json_decode(base64_decode($base64Backup),true):false;
		$backupInfo = $external?$base64Backup:$this->getBackup($id);
		$underscoreName = str_replace(' ', '_', $backupInfo['backup_name']);
		$this->log($transactionId,sprintf(_("Starting backup %s"),$underscoreName));
		$spooldir = $this->FreePBX->Config->get("ASTSPOOLDIR");
		$serverName = str_replace(' ', '_',$this->FreePBX->Config->get('FREEPBX_SYSTEM_IDENT'));
		$localPath = sprintf('%s/backup/%s',$spooldir,$underscoreName);
		$remotePath =  sprintf('/%s/%s',$serverName,$underscoreName);
		$tmpdir = sprintf('%s/backup/%s',sys_get_temp_dir(),$underscoreName);
		$this->fs->mkdir($tmpdir);
		//$pharname = sprintf('%s/backup-%s.tar',$localPath,time());
		//Legacy backup naming
		$pharname = sprintf('%s/%s%s-%s-%s.tar',$localPath,date("Ymd-His-"),time(),get_framework_version(),rand());
		$phargzname = sprintf('%s.gz',$pharname);
		$this->log($transactionId,sprintf(_("This backup will be stored locally at %s and is subject to maintinance settings"),$pharname));
		$phar = new \PharData($pharname);
		$phar->setSignatureAlgorithm(\Phar::SHA256);
		$storage_ids = $this->getStorageById($id);
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
		$validmods = $this->FreePBX->Modules->getModulesByMethod("backup");
		$backupItems = $this->getAll('modules_'.$id);
		if($external){
			$backupItems = $backupInfo['backup_items'];
		}
		$selectedmods = is_array($backupItems)?array_keys($backupItems):[];
		$errors = [];
		if(!$external){
			$maint = new Handler\Maintinance($this->FreePBX,$id);
		}
		foreach($selectedmods as $mod) {
			if(!in_array($mod, $validmods)){
				$err = sprintf(_("Could not backup module %s, it may not be installed or enabled"),$mod);
				$errors[] = $err;
				$this->log($transactionId,$err);
				continue;
			}
			$backup = new Handler\Backup($this->FreePBX);
			$backup->setBackupId($id);
			\modgettext::push_textdomain(strtolower($mod));
			$this->FreePBX->$mod->backup($backup);
			\modgettext::pop_textdomain();
			//Skip empty.
			if($backup->getModified() === false){
				$this->log($transactionId,sprintf(_("%s returned no data. This module may not impliment the new backup yet. Skipping"), $mod));
				$this->manifest['skipped'][] = $mod;
				continue;
			}
			$manifest['modules'][] = $mod;
			$moddata = $backup->getData();

			foreach ($moddata['dirs'] as $dir) {
				$dirs[] = backup__('files/' . $dir['path']);
			}

			foreach ($moddata['files'] as $file) {
				$srcpath = Handler\Backup::getPath($file);
				if (empty($srcpath)) {
					continue;
				}

				$srcpath = backup__($srcpath);
				$srcfile = $srcpath . '/' . $file['filename'];

				$destpath = backup__('files/' . ltrim($file['path'],'/'));
				$destfile = $destpath . '/' . $file['filename'];

				$dirs[] = $destpath;
				$files[$srcfile] = $destfile;
			}

			$modjson = $tmpdir . '/modulejson/' . $mod . '.json';
			if (!$this->fs->exists(dirname($modjson))) {
				$this->fs->mkdir(dirname($modjson));
			}
			file_put_contents($modjson, json_encode($moddata, JSON_PRETTY_PRINT));
			$files[$modjson] = 'modulejson/' . $mod . '.json';

			$data[$mod] = $moddata;
			$cleanup[$mod] = $moddata['garbage'];
		}

		foreach ($dirs as $dir) {
			$phar->addEmptyDir($dir);
		}
		$phar->setMetadata($manifest);

		/* We already have a list of files, so we'll let Phar add the files in bulk. */
		$phar->buildFromIterator(new \ArrayIterator(array_flip($files)));

		$phar->compress(\Phar::GZ);
		$signatures = $phar->getSignature();
		$pathinfo = pathinfo($phargzname);
		if(!$external){
			$remote = $remotePath.'/'.$pathinfo['basename'];
			$this->log($transactionId,_("Saving to selected Filestore locations"));
			$hash = false;
			if(isset($signatures['hash'])){
				$hash = $signatures['hash'];
			}
			foreach ($storage_ids as $location) {
				try {
					$location = explode('_', $location);
					$this->FreePBX->Filestore->put($location[0],$location[1],$phargzname,$remote);
					if($hash){
						$this->FreePBX->Filestore->put($location[0],$location[1],$hash,$remote.'.sha256sum');
					}
				} catch (\Exception $e) {
					$err = $e->getMessage();
					$this->log($transactionId,$err);
					$errors[] = $err;
				}
			}
		}
		$this->log($transactionId,_("Cleaning up"));
		foreach ($cleanup as $key => $value) {
			$this->log($transactionId,sprintf(_("Cleaning up data generated by %s"),$key));
			$this->fs->remove($value);
		}
		if(!$external){
			$maint->processLocal();
			$maint->processRemote();
		}
		if($external && empty($errors)){
			$this->fs->rename($phargzname,getcwd().'/'.$transactionId.'.tar.gz');
			$this->log($transactionId,sprintf(_("Remote transaction complete, file saved to %s"),getcwd().'/'.$transactionId.'tar.gz'));
		}
		$this->fs->remove($tmpdir);
		//$this->fs->remove($pharname);
		$this->fs->rename($pharname,$phargzname);
		$this->log($transactionId,_("Running post backup hooks"));
		$this->postBackupHooks($id, $signatures, $errors, $transactionId);
		if(!empty($errors)){
			$this->log($transactionId,_("Backup finished with but with errors"));
			$this->processNotifications($id, $transactionId, $errors);
			$this->setConfig('errors',$errors,$transactionId);
			$this->setConfig('log',$this->sessionlog[$transactionId],$transactionId);
			return $errors;
		}
		$this->log($transactionId,_("Backup completed successfully"));
		$this->processNotifications($id, $transactionId, []);
		$this->setConfig('log',$this->sessionlog[$transactionId],$transactionId);
		return $signatures;
	}

	public function doRestore() {
		$tmpdir = sys_get_temp_dir() . '/backup/';
		$this->fs->Remove($tmpdir);

		// TODO Get an archive via filestore selection.
		foreach (glob($this->FreePBX->Config->get("ASTSPOOLDIR") . '/backup-*.tar.gz') as $restorefile) {
			$pharname = $restorefile;
		}
		$phar = new \PharData($pharname);
		$phar->extractTo($tmpdir);

		$data = [];
		$dirs = [];
		$files = [];

		$mods = $this->FreePBX->Modules->getModulesByMethod("restore");
		foreach($mods as $mod) {
			$modjson = $tmpdir . '/modulejson/' . $mod . '.json';
			$moddata = json_decode(file_get_contents($modjson), true);
			$restore = new Handler\Restore($this->FreePBX, $moddata);
			\modgettext::push_textdomain(strtolower($mod));
			$this->FreePBX->$mod->restoreModule($restore);
			\modgettext::pop_textdomain();
			$moddata['dirs'] = $restore->getDirs();
			foreach ($moddata['dirs'] as $dir) {
				$destpath = Handler\Backup::getPath($dir);
				if (empty($destpath)) {
					/* We couldn't create a valid path.  Skip it. */
					// TODO Fail?  Display warning?
					continue;
				}

				$destpath = backup__($destpath);

				$dirs[] = $destpath;
			}

			$moddata['files'] = $restore->getFiles();
			foreach ($moddata['files'] as $file) {
				$destpath = Handler\Backup::getPath($file);
				if (empty($destpath)) {
					/* We couldn't create a valid path.  Skip it. */
					// TODO Fail?  Display warning?
					continue;
				}

				$srcpath = backup__($tmpdir . 'files/' . $file['path']);
				$srcfile = $srcpath . '/' . $file['filename'];

				$destpath = backup__($destpath);
				$destfile = $destpath . '/' . $file['filename'];

				$dirs[] = $destpath;
				$files[$srcfile] = $destfile;
			}

			$data[$mod] = $moddata;
		}

		$this->restoreDirs($dirs);
		$this->restoreFiles($files);

		$this->fs->remove($tmpdir);

		return $data;
	}

	/**
	 * Runs maintinance on filesystems (cleans up old stuff per user settings)
	 * Passing spool/spool for storagedriver and storage id will perform local maintinance
	 * @param  string $storagedriver the driver such as FTP, S3 etc
	 * @param  string $storageid     the storage item id
	 * @param  string $backupName    the name of the backup
	 * @param  string $transactionId the transaction id for the currently running process
	 * @return mixed              true or array of errors
	 */
	public function doMaintinance($backupId,$transactionId){

		$backupInfo = $this->getBackup($backupId);
		$underscoreName = str_replace(' ', '_', $backupInfo['backup_name']);
		$spooldir = $this->FreePBX->Config->get("ASTSPOOLDIR");
		$dir = sprintf('%s/backup/%s',$spooldir,$underscoreName);
		$this->log($transactionId,_("Performing Local Maintinance"));
		$maintfiles = [];
		$files = new \GlobIterator($dir.'/*.tar.gz');
		foreach ($files as $file) {
			$name = $file->getBasename();
			$date = substr($name, 7,10);
			$maintfiles[$date] = $file->getPath();
		}
		asort($maintfiles,SORT_NUMERIC);
		if(isset($backupInfo['maintruns']) && $backupInfo['maintruns'] > 1){
			$remove = array_slice($maintfiles,$backupInfo['maintruns'],null,true);
			foreach ($remove as $key => $value) {
				unlink($value);
			}
		}
		if(isset($backupInfo['maintage']) && $backupInfo['maintage'] > 1){
			$secondsperday = 86400;
			$age = ($backupInfo['maintage'] * $secondsperday);
			$now = time();
			foreach ($maintfiles as $key => $value) {
				if(((int)$key + $age) > $now){
					unlink($value);
				}
			}
		}
		$this->log($transactionId,_("Local Maintinance Complete"));

		$this->log($transactionId,sprintf(_("Performing maintinance on %s - %s"),$storagedriver,$storageid));
		$remote = sprintf('/backup/%s/%s/',$this->serverName,$backupName);
		try {
			$files = $this->FreePBX->ls($storagedriver,$storageid,$path);
		} catch (\Exception $e) {
			$this->log($transactionId,sprintf(_("Failed to get a file list for  %s - %s"),$storagedriver,$storageid));
			return false;
		}
		foreach ($files as $file) {

		}
		$this->log($transactionId,sprintf(_("Maintinance complete on %s - %s"),$storagedriver,$storageid));
	}

	//UTILITY

	/**
	 * Wrapper for Ramsey UUID so we don't have to put the full namespace string everywhere
	 * @return string UUIDv4
	 */
	public function generateId(){
		return \Ramsey\Uuid\Uuid::uuid4()->toString();
	}

	// TODO rename function
	// TODO Use Hooks->processHooks?
	public function backupSettingsMagic() {
		$settings = '';
		$mods = $this->FreePBX->Modules->getModulesByMethod("backupSettings");
		foreach($mods as $mod) {
			\modgettext::push_textdomain(strtolower($mod));
			$settings .= $this->FreePBX->$mod->backupSettings();
			\modgettext::pop_textdomain();
		}
		return $settings;
	}

	private function restoreDirs($dirs) {
		if (!$this->fs->exists($dirs)) {
			$this->fs->mkdir($dirs);
		}

		return;
	}

	private function restoreFiles($files) {
		foreach ($files as $src => $dest) {
			$this->fs->copy($src, $dest, true);
		}
	}

	public function processBackupSettings($id,$settings){
		 $this->FreePBX->Hooks->processHooks($id,$settings);
	}
	public function getBackupSettings($id){
		 return $this->FreePBX->Hooks->processHooks($id);
	}
	//TODO: Handle local hooks
	public function preBackupHooks($id = '', $transactionId = ''){
		$this->FreePBX->Hooks->processHooks($id,$transactionId);
	}
	public function postBackupHooks($id = '', $transactionId=''){
		$this->FreePBX->Hooks->processHooks($id,$transactionId);
	}
	public function preRestoreHooks($id = '', $transactionId = ''){
		$this->FreePBX->Hooks->processHooks($id,$transactionId);
	}
	public function postRestoreHooks($id = '', $transactionId=''){
		$this->FreePBX->Hooks->processHooks($id,$transactionId);
	}


	//TODO: Make this do spmething... Maybe kvstore then longpoll in the UI (that it the dream)
	public function log($transactionId = '', $message = ''){
		$this->FreePBX->Hooks->processHooks($transactionId,$message);
		$entry = sprintf('%s [%s] - %s', date('c'), $transactionId, $message);
		echo $entry.PHP_EOL;
		$this->sessionlog[$transactionId][] = $entry;
	}

	public function processNotifications($id, $transactionId, $errors){
		$backupInfo = $this->getBackup($id);
		if(!isset($backupInfo['backup_email']) || empty($backupInfo['backup_email'])){
			return false;
		}
		if(!isset($backupInfo['backup_emailtype']) || empty($backupInfo['backup_emailtype'])){
			return false;
		}
		$serverName = $this->FreePBX->Config->get('FREEPBX_SYSTEM_IDENT');
		$emailSubject = sprintf(_('Backup %s success for %s'),$backupInfo['backup_name'], $serverName);
		if(!empty($errors)){
			$emailSubject = sprintf(_('Backup %s failed for %s'),$backupInfo['backup_name'], $serverName);
		}
		$emailbody = [];
		if(isset($backupInfo['backup_emailtype']) && $backupInfo['backup_emailtype'] == 'failure'){
			if(empty($errors)){
				return false;
			}
		}
		if(isset($backupInfo['backup_emailtype']) && $backupInfo['backup_emailtype'] == 'success'){
			if(!empty($errors)){
				return false;
			}
		}
		$emailbody[] = _("Backup Information");
		$emailbody[] = sprintf(_("Backup Name: %s"),$backupInfo['backup_name']);
		$emailbody[] = sprintf(_("Backup Description: %s"),$backupInfo['backup_description']);
		$emailbody[] = sprintf(_("Server Name: %s"),$serverName);
		$emailbody[] = PHP_EOL;
		if(isset($this->sessionlog[$transactionId])){
			$emailbody[] = _("Backup Log");
			foreach ($this->sessionlog[$transactionId] as $line) {
				$emailbody[] = $line;
			}
		}
		if(!empty($errors)){
			$emailbody[] = _("Error Log");
			foreach ($errors as $line) {
				$emailbody[] = $line;
			}
		}
		$email = \FreePBX::Mail();
		$email->setSubject($emailSubject);
		$email->setTo($backupInfo['backup_email']);
		$email->setBody(implode(PHP_EOL, $emailbody));
		return $email->send();
	}
}
