<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 */
namespace FreePBX\modules;
use FreePBX\modules\Backup\Handlers as Handler;
use FreePBX\modules\Filestore\Modules\Remote as FilestoreRemote;
use FreePBX\modules\Backup\Models\BackupFile;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\LockHandler;
use Monolog\Handler\SwiftMailerHandler;
use Monolog\Handler\BufferHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter as Formatter;
use FreePBX\modules\Backup\Modules\Backupjobs;
use FreePBX\modules\Backup\Modules\Servers;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FreePBX_Helpers;
use BMO;
use splitbrain\PHPArchive\Tar;
class Backup extends FreePBX_Helpers implements BMO {
	const DEBUG = true;
	public $swiftmsg = false;
	public function __construct($freepbx = null) {
		if ($freepbx == null) {
				throw new Exception('Not given a FreePBX Object');
		}
		$this->FreePBX        = $freepbx;
		$this->db             = $freepbx->Database;
		$this->mf             = \module_functions::create();
		$this->fs             = new Filesystem;
		$this->backupFields   = ['backup_name','backup_description','backup_items','backup_storage','backup_schedule','schedule_enabled','maintage','maintruns','backup_email','backup_emailtype','immortal', 'warmspareenabled', 'warmspare_remotetrunks', 'warmspare_remotenat', 'warmspare_remotebind', 'warmspare_remotenat', 'warmspare_remotedns', 'warmspare_remoteapply', 'warmspare_remoteip', 'warmspare_user', 'publickey'];
		$this->templateFields = [];
		$this->serverName     = $this->FreePBX->Config->get('FREEPBX_SYSTEM_IDENT');
		$this->sessionlog     = [];
		$this->backupHandler  = null;
		$this->restoreHandler = null;
		$this->logger         = $this->FreePBX->Logger();
		$this->logpath        = $this->getConfig('logpath');
		$this->logpath        = !empty($this->logpath)?$this->logpath:'/var/log/asterisk/backup.log';
		$this->logger->createCustomLog('Backup', $this->logpath,true);
		$output = "%level_name%: %message%\n";

		$this->formatter = new Formatter\LineFormatter($output);

		if(php_sapi_name() == 'cli' || php_sapi_name() == 'phpdbg'){
			$handler = new StreamHandler("php://stdout",\Monolog\Logger::DEBUG);
			$handler->setFormatter($this->formatter);
			$this->logger->customLog->pushHandler($handler);
		}
		$this->loggingHooks = null;
	}

	public function install(){

		/** Oh... Migration, migration, let's learn about migration. It's nature's inspiration to move around the sea.
		 * We have split the functionality up so things backup use to do may be done by another module. The other module(s)
		 * May not yet be installed or may install after.  So we need to keep a kvstore with the various data and when installing
		 * The other modules will checkin on install and process the data needed by them.
		 **/

		$dbexist = $this->db->query("SHOW TABLES LIKE 'backup'")->rowCount();
		if($dbexist === 1){
			out(_("Migrating legacy backupjobs"));
			out(_("Moving servers to filestore"));
			$servers = new Servers($this->FreePBX);
			$servers->process();
			out(_("Migrating legacy backups to the new backup"));
			$jobs = new Backupjobs($this->FreePBX);
			$jobs->process(); 

			out(_("Cleaning up old data"));
			$tables = [
				'backup',
				'backup_cache',
				'backup_details',
				'backup_items',
				'backup_server_details',
				'backup_servers',
				'backup_template_details',
				'backup_templates',
			];
			foreach ($tables as $table) {
				out(sprintf(_("Removing table %s."),$table));
				if(!DEBUG){
					$this->db->prepare("DROP TABLE :table")->execute([':table' => $table]);
				}
			}
		}
	}

	public function uninstall(){
	}

	public function doConfigPageInit($page) {
		if($page == 'backup'){
			/** Delete Backup */
			if(isset($_REQUEST['action']) && $_REQUEST['action'] == 'delete'){
				return $this->deleteBackup($_REQUEST['id']);
			}
			/** Update Backup */
			if(isset($_POST['backup_name'])){
				$this->importRequest();
				return $this->updateBackup();
			}
			/** Set global settings */
			if(isset($_POST['fromemail'])){
				$this->importRequest();
				$from = $this->getReq('fromemail','backup@pbx.local');
				$path = $this->getReq('logpath','/var/log/asterisk/backup.log');
				return $this->setMultiConfig(['fromemail'=> $from,'logpath' => $path],'globalsettings');
			}
		}
	}


	public function getActionBar($request) {
		/** No buttons unless we are in a view */
		if(!isset($request['view'])){
			return [];
		}
		if($request['view' == 'run']){
			return [];
		}
		/** Process restore file Buttons */
		if($request['view'] == 'processrestore'){
			return [
				'run' => [
					'name'  => 'runrestore',
					'id'    => 'runrestore',
					'value' => _("Run Restore")
				],
				'back' => [
					'name'  => 'goback',
					'id'    => 'goback',
					'value' => _("Go Back")
				]
			];
		}
		/**	Generic button set*/
		$buttons = [
			'reset' => [
				'name'  => 'reset',
				'id'    => 'reset',
				'value' => _('Reset'),
			],
			'submit' => [
				'name'  => 'submit',
				'id'    => 'submit',
				'value' => _('Save'),
			],
			'run' => [
				'name'  => 'run',
				'id'    => 'run_backup',
				'value' => _('Save and Run'),
			],
			'delete' => [
				'name'  => 'delete',
				'id'    => 'delete',
				'value' => _('Delete'),
			],
		];
		if('backup_restore' == $request['display']){
			unset($buttons['run']);
		}

		/** If we are not in an edit screen kill the run and delete */
		if(!isset($request['id']) || empty($request['id'])){
			unset($buttons['delete']);
			unset($buttons['run']);
		}
		return $buttons;
	}

	/**
	 * Ajax Request for BMO
	 * @param string $req     [description]
	 * @param [type] $setting [description]
	 */
	public function ajaxRequest($command, &$setting) {
		switch ($command) {
			case 'getJSON'          :
			case 'run'              :
			case 'runRestore'       :
			case 'remotedownload'	:
			case 'deleteRemote'		:
			case 'localdownload'    :
			case 'localRestoreFiles':
			case 'restoreFiles'     :
			case 'uploadrestore'    :
			case 'generateRSA'      :
			case 'deleteLocal'      :
			case 'runstatus'		:
				return true;
			default:
				return false;
		}
	}

	/**
	 * Ajax Module for BMO
	 */
	public function ajaxHandler() {
		switch ($_REQUEST['command']) {
			case 'deleteRemote':
				$server = $_REQUEST['id'];
				$file = $_REQUEST['file'];
				$server = explode('_', $server);
				if($this->deleteRemote($server[0], $server[1], $file)){
					return ['status' => true, "message" => _("File Deleted")];
				}
				return ['status' => false, "message" => _("Something failed, The file may need to be removed manually.")];

			case 'deleteLocal':
				$filepath = $this->pathFromId($_REQUEST['id']);
				if(!$filepath){
					return ['status' => false, "message" => _("Invalid ID Provided")];
				}
				$file = new \SplFileObject($filepath);
				if(!$file->isWritable()){
					return ['status' => false, "message" => _("We don't have permissions to this file")];
				}
				if(unlink($filepath)){
					return ['status' => true, "message" => "File Removed"];
				}
				return ['status' => false, "message" => _("We can't seem to delete the chosen file")];
			case 'generateRSA':
				$ssh = new FilestoreRemote();
				$ret = $ssh->generateKey('/home/asterisk/.ssh');
			return ['status' => $ret];
			case 'runstatus':
				if(!isset($_GET['id']) || !isset($_GET['transaction'])){
					return ['status' => 'stopped', 'error' => _("Missing id or transaction")];
				}
				$job = $_GET['transaction'];
				$logdata = $this->getConfig('sessionlog');
				$log = is_array($logdata)?$logdata:[];
				$log = implode(PHP_EOL,reset($log));
				$log = '<pre>'.$log.'</pre>';
				$buid = $_GET['id'];
				$lockModule = new LockHandler($job.'.'.$buid);
				if (!$lockModule->lock()) {
					$lockModule->release();
					return ['status' => 'running', 'log' => $log];
				}
				return ['status' => 'stopped', 'log' => $log];
			case 'uploadrestore':
				$response = new Response(null,400,['Content-Type' => 'application/json']);
				$err = false;
				if (!isset($_FILES['file'])) {
					$err = ['status' => false, 'error' => _("No file provided")];
				}
				if ($_FILES['file']['error'] !== 0) {
					$err = ['status' => false, 'err' => $_FILES['file']['error'], 'message' => _("File reached the server but could not be processed")];
				}

				if ($_FILES['file']['type'] != 'application/x-gzip') {
					//$err = ['status' => false, 'mime' => $_FILES['file']['type'], 'message' => _("The uploaded file type is incorrect and couldn't be processed")];
				}
				if($err !== false){
					$response->setContent(json_encode($err));
					$response->send();
					exit();
				}
				$spooldir = $this->FreePBX->Config->get("ASTSPOOLDIR");
				$path = sprintf('%s/backup/uploads/', $spooldir);
				$finalname = $path.'/'. $_FILES['file']['name'];
				$tmp_name = $_FILES['file']['tmp_name'];
				$filename = $_FILES['file']['name'];
				$num = $_POST['dzchunkindex'];
				$num_chunks = $_POST['dztotalchunkcount'];
				$uuid = $_POST['dzuuid'];
				$partialPath = sprintf('%s/backup/uploads/%s/', $spooldir,$uuid);
				$target_file = $partialPath.$filename;
				@mkdir($partialPath, 0755, true);
				move_uploaded_file($tmp_name, $partialPath.$filename.$num);
				if($num + 1 == $num_chunks){
					for ($i = 0; $i <= $num_chunks - 1; $i++) {

						$file = fopen($target_file . $i, 'rb');
						$buff = fread($file, 2097152);
						fclose($file);

						$final = fopen($finalname, 'ab');
						$write = fwrite($final, $buff);
						fclose($final);
						unlink($target_file . $i);
					}
					$file = $finalname;
					$this->setConfig(md5($file), $file, 'localfilepaths');
					$backupFile = new BackupFile($file);
					$meta = $backupFile->getMetadata();
					$this->setConfig('meta', $meta, md5($file));
					header("HTTP/1.1 200 Ok");
					return ['status' => true, 'md5' => md5($file)];
				}
				if ($num + 1 < $num_chunks) {
					header("HTTP/1.1 201 Created");
					break;
				}

				break;
			case 'localRestoreFiles':
				return $this->getLocalFiles();
			case 'restoreFiles':
				return $this->getAllRemote();
			case 'runRestore':
				$file = $this->pathFromId($_GET['fileid']);
				if(!$file){
					return ['status' => false, 'message' => _("Could not find a file for the id supplied")];
				}
				$jobid   = $this->generateId();
				$process = new Process('fwconsole backup --restore="'.$file.'" --transaction='.$jobid.' > /dev/null 2>&1 &');
				$process->disableOutput();
				try {
					$process->mustRun();
				} catch (\Exception $e) {
					return ['status' => false, 'message' => _("Couldn't run process."),'exception'=> $e->getMessage()];
				}
				$pid = $process->getPid();
				return ['status' => true, 'message' => _("Restore running"), 'process' => $pid, 'transaction' => $jobid];
			case 'run':
				if(!isset($_GET['id'])){
					return ['status' => false, 'message' => _("No backup id provided")];
				}
				$buid    = escapeshellarg($_GET['id']);
				$jobid   = $this->generateId();
				$warmspare = $this->getConfig('warmspareenabled', $buid) === 'yes';
				$command = 'fwconsole backup --backup=' . $buid . ' --transaction=' . $jobid . ' > /dev/null 2>&1 &';
				if($warmspare){
					$command .= ' --warmspare';
				}
				$process = new Process($command);
				$process->disableOutput();
				try {
					$process->mustRun();
				} catch (\Exception $e) {
					return ['status' => false, 'message' => _("Couldn't run process."),'exception'=> $e->getMessage()];
				}
				$pid = $process->getPid();
				return ['status' => true, 'message' => _("Backup running"), 'process' => $pid, 'transaction' => $jobid, 'backupid' => $buid];
			case 'getJSON':
				switch ($_REQUEST['jdata']) {
					case 'backupGrid':
						return array_values($this->listBackups());
					case 'backupStorage':
						$storage_ids = [];
						if(isset($_GET['id']) && !empty($_GET['id'])){
							$storage_ids = $this->getStorageByID($_GET['id']);
						}
						try {
							$fstype = $this->getFSType();
							$items  = $this->FreePBX->Filestore->listLocations($fstype);
							$return = [];
							foreach ($items['locations'] as $driver => $locations ) {
								$optgroup = [
									'label'    => $driver,
									'children' => []
								];
								foreach ($locations as $location) {
									$select       = in_array($driver.'_'.$location['id'], $storage_ids);
									$optgroup['children'][] = [
										'label'    => $location['name'],
										'title'    => $location['description'],
										'value'    => $driver.'_'.$location['id'],
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
					case 'backupItems'                   :
					$id  = isset($_GET['id'])?$_GET['id']: '';
					return $this->HandlerById($id);
					default:
						return false;
				}
			break;
			default:
				return false;
		}
	}
	public function ajaxCustomHandler() {

		switch($_REQUEST['command']){
			case 'remotedownload':
				$filepath = $this->remoteToLocal($_REQUEST['id'],$_REQUEST['filepath']);
			case 'localdownload':
				if(empty($_REQUEST['id'])){
					return false;
				}
				if(!isset($filepath)){
					$filepath = $this->getAll('localfilepaths');
					$filepath = isset($filepath[$_REQUEST['id']])?$filepath[$_REQUEST['id']]:false;
				}
				if(empty($filepath)){
					return false;
				}
				header("Content-disposition: attachment; filename=".basename($filepath));
				header("Content-type: application/octet-stream");
				readfile($filepath);
				exit;
		}
	}

	//TODO: This whole thing
	public function getRightNav($request) {
		return;
	}

	//Display stuff

	public function showPage($page){
		switch ($page) {
			case 'backup':
				if(isset($_GET['view']) && $_GET['view'] == 'run' || isset($_REQUEST['runit'])){
					return load_view(__DIR__.'/views/run.php',array('id' => $_REQUEST['id']));
				}
				if(isset($_GET['view']) && $_GET['view'] == 'settings'){
					$vars = $this->getAll('globalsettings');
					$vars = $vars?$vars:[];
					$vars['fromemail'] = isset($vars['fromemail'])?$vars['fromemail']:'backup@pbx.local';
					$vars['logpath']   = isset($vars['logpath'])?$vars['logpath']:'/var/log/asterisk/backup.log';
					$file = '/home/asterisk/.ssh/id_rsa.pub';
					if (!file_exists($file)) {
						$ssh = new FilestoreRemote();
						$ssh->generateKey('/home/asterisk/.ssh');
					}
					$data = file_get_contents($file);
					$vars['publickey'] = $data;
					return load_view(__DIR__.'/views/backup/settings.php',$vars);
				}
				if(isset($_GET['view']) && $_GET['view'] == 'newRSA'){
					return load_view(__DIR__.'/views/backup/rsa.php');
				}
				if(isset($_GET['view']) && $_GET['view'] == 'form'){
					$randcron          = sprintf('59 23 * * %s',rand(0,6));
					$vars              = ['id' => ''];
					$vars['backup_schedule'] = $randcron;
					if(isset($_GET['id']) && !empty($_GET['id'])){
						$vars              = $this->getBackup($_GET['id']);
						$vars['backup_schedule'] = !empty($vars['backup_schedule'])?$vars['backup_schedule']:$randcron;
						$vars['id']              = $_GET['id'];
					}
					$warmsparedisable = $this->getConfig('warmsparedisable');
					$vars['transfer']       = $this->getConfig('transferdisable');
					$vars['warmspare']      = '';
					if(empty($warmsparedisable)){
						$warmsparedefaults = [
							'warmspare_user'   => 'root',
							'warmspare_remote' => 'no',
							'warmspare_enable' => 'no',
						];
						$settings = $this->getConfig('warmsparesettings');
						$settings = $settings?$settings:[];
						foreach($warmsparedefaults as $key => $value){
							$value = isset($settings[$key])?$settings[$key]:$value;
							$vars[$key]  = $value;
						}

						$vars['warmspare'] = load_view(__DIR__.'/views/backup/warmspare.php',$vars);
					}
					$vars['transfer'] = '';
					if(!$transferdisabled){
						$vars['transfer'] = '<li role="presentation" class="'.(isset($_GET['view']) && $_GET['view'] == 'yes')?"active":"".'"><a href="?display=backup&view=transfer">'. _("System Transfer").'</a></li>';
					}
					return load_view(__DIR__.'/views/backup/form.php',$vars);
				}
				if(isset($_GET['view']) && $_GET['view'] == 'download'){
					return load_view(__DIR__.'/views/backup/download.php');
				}
				if(isset($_GET['view']) && $_GET['view'] == 'transfer'){
					return load_view(__DIR__.'/views/backup/transfer.php');
				}
				return load_view(__DIR__.'/views/backup/grid.php');
			case  'restore'                           :
			$view = isset($_GET['view'])?$_GET['view']: 'default';
				switch ($view) {
					case 'processrestore':
						if(!isset($_GET['id']) || empty($_GET['id'])){
							return load_view(__DIR__.'/views/restore/landing.php',['error' => _("No id was specified to process. Please try submitting your file again.")]);
						}
						if($_GET['type'] == 'local'){
							$fileid = $_GET['id'];
							$path = $this->pathFromId($_GET['id']);
						}
						if($_GET['type'] == 'remote'){
							$path = $this->remoteToLocal($_GET['id'],$_GET['filepath']);
							$fileid = md5($path);
						}
						if(empty($path)){
							return load_view(__DIR__.'/views/restore/landing.php',['error' => _("Couldn't find your file, please try submitting your file again.")]);
						}
						if($path){
							$file = new BackupFile($path);
							$manifest = $file->getMetadata($path);
						}
						$vars['meta']     = $manifest;
						$vars['date']     = $this->FreePBX->View->getDateTime($manifest['date']);
						$vars['jsondata'] = $this->moduleJSONFromManifest($manifest);
						$vars['id']       = $_GET['id'];
						$vars['fileid']   = $fileid;
						return load_view(__DIR__.'/views/restore/processRestore.php',$vars);
					default:
						return load_view(__DIR__.'/views/restore/landing.php');
				}
			break;
			default:
				return load_view(__DIR__.'/views/backup/grid.php');
		}
	}

	public function getBackupSettingsDisplay($module,$id = ''){
		$module = ucfirst($module);
		if($module === 'Backup'){
			return;
		}
		$class = $this->FreePBX->$module;
		if( method_exists($class, 'getBackupSettingsDisplay')){
			return '<div class="hooksetting">'. $class->getBackupSettingsDisplay($id).'</div>';
		}
		return '<div class="hooksetting">'._("No user configurable settings")."</div>";
	}

	//Getters


	/**
	 * Sets hooks for external files in to a queue
	 * @param string $type load inbound, outbound, both
	 * @return void
	 */
	public function getHooks($type = 'all'){
		if($type == 'backup' || $type == 'all'){
			$this->preBackup  = new \SplQueue();
			$this->postBackup = new \SplQueue();
		}
		if($type == 'restore' || $type == 'all'){
			$this->preRestore  = new \SplQueue();
			$this->postRestore = new \SplQueue();
		}
		$hookpath      = getenv('BACKUPHOOKDIR');
		$hookpath      = $hookpath?$hookpath:'/home/asterisk/Backup';

		if (!file_exists($hookpath)) {
			return;
		}

		$filehooks     = ['BACKUPPREHOOKS' => 'preBackup','RESTOREPREHOOKS' => 'preRestore','BACKUPPOSTHOOKS' => 'postBackup','RESTOREPOSTHOOKS' => 'postRestore'];
		foreach($filehooks as $hook => $objName){
			$env = getenv($hook);
			if(empty($env)){
				continue;
			}
			$env = explode(',',$env);
			$env = !empty($env)?$env:[];
			foreach($env as $file){
				if(!empty($this->$objName)){
					$this->$objName->push($file);
				}
			}
		}

		foreach (new \DirectoryIterator($hookpath) as $fileInfo) {
			if($fileInfo->isFile() && $fileInfo->isReadable() && $fileInfo->isExecutable()){
				$fileobj = $fileInfo->openFile('r');
				while (!$fileobj->eof()) {
					$found = preg_match("/(pre|post):(backup|restore)/", $fileobj->fgets(), $out);
	   				if($found === 1){
						$hooktype = $out[1].$out[2];
						$filename = $hookpath.'/'.$fileobj->getFilename();
						if($hooktype == 'prebackup' && !empty($this->preBackup)){
							$this->preBackup->push($filename);
						}
						if($hooktype == 'postbackup' && !empty($this->postBackup)){
							$this->postBackup->push($filename);
						}
						if($hooktype == 'prerestore' && !empty($this->preRestore)){
							$this->preRestore->push($filename);
						}
						if($hooktype == 'postrestore' && !empty($this->postRestore)){
							$this->postRestore->push($filename);
						}
						break;
					}
				}
			}
		}
	}

	public function pathFromId($id){
		return $this->getConfig($id,'localfilepaths');
	}
	/**
	 * Get storage locations by backup ID
	 * @param  string $id backup id
	 * @return array  array of backup locations as DRIVER_ID
	 */
	public function getStorageById($id){
		$storage = $this->getConfig('backup_storage',$id);
		return is_array($storage)?$storage: [];
	}

	public function attachEmail($backupInfo){
		$envto      = getenv('BACKUPEMAILTO');
		if(!empty($envto)){
			$backupInfo['backup_email'] = $envto;
		}
		if(!isset($backupInfo['backup_email']) || empty($backupInfo['backup_email'])){
			return false;
		}
		if(!isset($backupInfo['backup_emailtype']) || empty($backupInfo['backup_emailtype'])){
			return false;
		}

		$serverName   = $this->FreePBX->Config->get('FREEPBX_SYSTEM_IDENT');
		$emailSubject = sprintf(_('Backup %s success for %s'),$backupInfo['backup_name'], $serverName);
		if(!empty($this->errors)){
			$emailSubject = sprintf(_('Backup %s failed for %s'),$backupInfo['backup_name'], $serverName);
		}

		if(isset($backupInfo['backup_emailtype']) && $backupInfo['backup_emailtype'] == 'success'){
			if(!empty($this->errors)){
				return false;
			}
		}
		$from    = $this->getConfig('fromemail');
		$envfrom = getenv('BACKUPEMAILFROM');
		if(!empty($envfrom)){
			$from = $envfrom;
		}
		if(empty($from)){
			return;
		}
		$transport = \Swift_MailTransport::newInstance();
		$this->swiftmsg = \Swift_Message::newInstance();
		$this->swiftmsg->setContentType("text/html");
		$swift = \Swift_Mailer::newInstance($transport);
		$this->handler = new BufferHandler(new SwiftMailerHandler($swift, $this->swiftmsg, \Monolog\Logger::INFO), 0, \Monolog\Logger::INFO);
		$formatter = new Formatter\HtmlFormatter();
		$this->handler->SetFormatter($formatter);
		$this->logger->customLog->pushHandler($this->handler);
		$this->swiftmsg->setFrom($from);
		$this->swiftmsg->setSubject($emailSubject);
		$this->swiftmsg->setTo($backupInfo['backup_email']);
	}

	/**
	 * Gets the appropriate filesystem types to pass to filestore.
	 * @return mixed if hooks are present it will present an array, otherwise a string
	 */
	public function getFSType(){
		$types = $this->FreePBX->Hooks->processHooks();
		$ret   = [];
		foreach ($types as $key => $value) {
			$value = is_array($value)?$value:[];
			$ret   = array_merge($ret,$value);
		}
		return !empty($ret)?$ret: 'backup';
	}

	/**
	 * List all backups
	 * @return array Array of backup items
	 */
	public function listBackups() {
		$return = $this->getAll('backupList');
		return is_array($return)?$return: [];
	}

	/**
	 * Get all settings for a specific backup id
	 * @param  string $id backup id
	 * @return array  an array of backup settings
	 */
	public function getBackup($id){
		$data   = $this->getAll($id);
		$return = [];
		foreach ($this->backupFields as $key) {
			$return[$key] = isset($data[$key])?$data[$key]:'';
		}
		return $return;
	}

	/**
	 * Gets local backup files from the system
	 * @
	 * @return array file list
	 */
	public function getLocalFiles(){
		$files     = [];
		$base      = $this->FreePBX->Config->get('ASTSPOOLDIR');
		$base      = $base?$base:'/var/spool/asterisk';
		$backupdir = $base . '/backup';

		$this->fs->mkdir($backupdir);

		$Directory = new \RecursiveDirectoryIterator($backupdir,\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::CURRENT_AS_FILEINFO);
		$Iterator  = new \RecursiveIteratorIterator($Directory,\RecursiveIteratorIterator::LEAVES_ONLY);
		$this->delById('localfilepaths');
		foreach($Iterator as $k => $v){
			$path       = $v->getPathInfo()->getRealPath();
			$buname     = $v->getPathInfo()->getBasename();
			$buname     = str_replace('_',' ',$buname);
			$backupFile = new BackupFile($k);
			$backupinfo = $backupFile->backupData();
			if(empty($backupinfo)){
				continue;
			}
			$this->setConfig(md5($k),$k,'localfilepaths');
			$backupinfo['path'] = $path;
			$backupinfo['id']   = md5($k);
			$backupinfo['name'] = $buname;
			$backupinfo['date'] = $this->FreePBX->View->getDateTime($backupinfo['timestamp']);
			$files     []       = $backupinfo;
		}
		return $files;
	}

	/**
	 * Get modules for a specific backup id returned in an array
	 * @param  string  $id              The backup id
	 * @param  boolean $selectedOnly    Only return the modules selected
	 * @param  boolean $includeSettings Include settings html for rendering in the UI
	 * @return array   list of module data
	 */
	public function HandlerById($id = '',$selectedOnly = false, $includeSettings = true){
		if(empty($this->backupHandler)){
			$this->backupHandler = new Handler\Backup($this->FreePBX);
		}
		$modules  = $this->backupHandler->getModules();
		$selected = $this->getAll('modules_'.$id);
		$selected = is_array($selected)?array_keys($selected):[];
		if($selectedOnly){
			return $selected;
		}
		$ret = [];
		foreach ($modules as $module) {
			$item = [
				'modulename' => $module,
				'selected'   => in_array($module, $selected),
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
			$warmspare = $this->getConfig('warmspareenabled', $buid) === 'yes';
			if($enabled === 'yes'){
				$schedule = $this->getBackupSetting($id, 'backup_schedule');
				$command  = sprintf('/usr/sbin/fwconsole backup backup=%s %s > /dev/null 2>&1',$id, $warmspare ? '--warmspare' : '');
				$this->FreePBX->Cron->removeAll($command);
				$this->FreePBX->Cron->add($schedule.' '.$command);
				return true;
			}
		}
		//Clean slate
		$allcrons = $this->FreePBX->Cron->getAll();
		$allcrons = is_array($allcrons)?$allcrons:[];
		foreach ($allcrons as $cmd) {
			if (strpos($cmd, 'fwconsole backup') !== false) {
				$this->FreePBX->Cron->remove($cmd);
			}
		}
		$backups = $this->listBackups();
		foreach ($backups as $key => $value) {
			$enabled = $this->getBackupSetting($key, 'schedule_enabled');
			$warmspare = $this->getConfig('warmspareenabled', $key) === 'yes';
			if($enabled === 'yes'){
				$schedule = $this->getBackupSetting($key, 'backup_schedule');
				$command  = sprintf('/usr/sbin/fwconsole backup backup=%s %s> /dev/null 2>&1',$key, $warmspare ? '--warmspare' : '');
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
	public function updateBackup(){
		$data = [];
		$data['id'] = $this->getReq('id');
		if(empty($data['id'])){
			$data['id'] = $this->generateID();
		}
		foreach ($this->backupFields as $col) {
			//This will be set independently
			if($col == 'immortal'){
				continue;
			}

			$value = $this->getReqUnsafe($col,'');
			$this->updateBackupSetting($data['id'], $col, $value);
		}
		$description = $this->getReq('backup_description',sprintf(_('Backup %s'),$this->getReq('backup_name')));
		$this->setConfig($data['id'],array('id' => $data['id'], 'name' => $this->getReq('backup_name',''), 'description' => $description),'backupList');
		if($this->getReq('backup_items','unchanged') !== 'unchanged'){
			$backup_items = json_decode(html_entity_decode($this->getReq('backup_items',[])),true);
			$this->setModulesById($data['id'], $backup_items);
		}
		//We expect this to be JSON so we don't sanitize it.
		$data['backup_items_settings'] = $this->getReqUnsafe('backup_items_settings', 'unchanged');
		if($data['backup_items_settings'] !== 'unchanged' ){
			$this->processBackupSettings($data['id'], json_decode($data['backup_items_settings'],true));
		}
		$this->scheduleJobs($id);
		return $id;
	}
	
	public function processBackupSettings($id = '', $data = []){
		$modules = $this->FreePBX->Modules->getModulesByMethod('processBackupSettings');
		foreach ($modules as $module) {
			if($module === 'Backup'){
				continue;
			}
			$this->FreePBX->$module->processBackupSettings($id, $data);
		}
	}

	/**
	 * Sets an individual setting
	 *
	 * @param string $id Backup id
	 * @param string $setting Backup setting
	 * @param boolean $value
	 * @return void
	 */
	public function updateBackupSetting($id, $setting, $value=false){
		$this->setConfig($setting,$value,$id);
		if($setting == 'backup_schedule'){
			$this->scheduleJobs($id);
		}
	}
	/**
	 * Get individual backup setting
	 *
	 * @param string $id backup id
	 * @param string $setting setting name
	 * @return void
	 */
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


	//UTILITY

	public function processDependencies($deps = []){
		$ret = true;
		foreach($deps as $dep){

			if($this->FreePBX->Modules->getInfo(strtolower($dep),true)){
				continue;
			}
			try{
				$this->mf->install(strtolower($dep),true);
			}catch(\Exception $e){
				$ret = false;
				break;
			}
		}
		return $ret;
	}

	/**
	 * Wrapper for Ramsey UUID so we don't have to put the full namespace string everywhere
	 * @return string UUIDv4
	 */
	public function generateId(){
		return \Ramsey\Uuid\Uuid:: uuid4()->toString();
	}

	/**
	 * Logger: logs via monolog
	 *
	 * @param string $transactionId running transaction
	 * @param string $message message to log
	 * @return void
	 */
	public function log($transactionId = '', $message = '',$level = 'INFO'){
		$this->sessionlog[$transactionId][] = $message;
		$this->setConfig('sessionlog',$this->sessionlog);
		$this->logger->logWrite($transactionId,$message,true,$level);
	}

	/**
	 * Process Notifications, emails etc
	 *
	 * @param string $id Backup id
	 * @param string $transactionId Running transaction
	 * @param array $errors
	 * @return void
	 */
	public function processNotifications($id, $transactionId, $errors){
		$serverName = str_replace(' ', '_', $this->FreePBX->Config->get('FREEPBX_SYSTEM_IDENT'));
		$serverfilename = str_replace(' ','-',$serverName);
		$filename       = sprintf('%s-%s-backup.log',$serverfilename,time());
		$path           = '/var/log/asterisk/backup.log';
		if($this->getConfig('logpath')){
			$path = $this->getConfig('logpath');
		}
		if(!$this->swiftmsg){
			return;
		}
		if(empty($this->swiftmsg->getTo())){
			return;
		}
		try {
			$this->swiftmsg->attach(\Swift_Attachment::fromPath($path)->setFilename($filename));
			$this->handler->close();
		}catch(\Exception $e){
			dbug($e->getMessage());
		}
	}

	/**
	 * Attach monolog handlers from BMO hooks
	 *
	 * @param string $type backup or restore
	 * @return void
	 */
	public function attachLoggers($type){
		if(!is_object($this->loggingHooks)){
			$this->loggingHooks = new \SplObjectStorage();
		}
		$this->FreePBX->Hooks->processHooks($this->loggingHooks,$type);
		foreach($this->loggingHooks as $hook){
			try{
				$this->logger->customLog->pushHandler($hook);
			}catch(\Exception $e){
				//don't  let a bad apple mess it up for everyone
				dbug('Backup: custom handler skipped');
				dbug($e->getMessage());
				continue;
			}
		}
	}

	/**
	 * Convert path params to actual path
	 * @static Backup::getPath
	 * @param string $string path
	 * @return void
	 */
	static function getPath($string){
		if (!preg_match("/__(.+)__/", $string, $out)) {
			return $string;
		}
		$path = $this->FreePBX->Config->get($out[1]);
		if($path){
			return str_replace($out[0], $path, $string);
		}
		return $string;
	}

	/**
	 * Convert file list from the manifest into a json string
	 *
	 * @param array $data data from manifest
	 * @return string JSON representation of files.
	 */
	public function moduleJSONFromManifest($data){
		$return = [];
		if(!isset($data['modules'])){
			return [];
		}
		foreach($data['modules'] as $module){
			$name    = $module['module'];
			$version = $module['version'];
			$status  = ($this->FreePBX->Modules->checkStatus(strtolower($name)))?_("Enabled"):_("Uninstalled or Disabled");
			$return[] = [
				'modulename' => $name,
				'version'    => $version,
				'installed'  => $status
			];
		}
		return json_encode($return);
	}

	public function deleteRemote($driver, $id, $path){
		return $this->FreePBX->Filestore->delete($driver, $id, $path);
	}

	public function getAllRemote(){
		$final = [];
		$serverName = str_replace(' ', '_',$this->FreePBX->Config->get('FREEPBX_SYSTEM_IDENT'));
		$ret = $this->FreePBX->Filestore->listAllFilesByPath($serverName);
		foreach($ret as $dname => $driver){
			foreach($driver as $id => $location){
				if(!isset($location['results'])){
					continue;
				}
				foreach($location['results'] as $file){
					if($file['type'] == 'dir'){
						continue;
					}
					$backupFile = new BackupFile($file['path']);
					$info = $backupFile->backupData();
					if($info['isCheckSum']){
						continue;
					}
					$final[] = [
						'id' => $dname.'_'.$id,
						'type' => $dname,
						'file' => $file['path'],
						'framework' => $info['framework'],
						'date' => $this->FreePBX->View->getDateTime($info['timestamp']),
						'name' => str_replace('_',' ',explode('/',$file['dirname'])[1]),
					];
				}
			}
		}
		return $final;
	}
	public function remoteToLocal($location,$file){
		$parts = explode('_',$location);
		$fileparts = array_slice(explode('/',$file),-2);
		$spooldir = $this->FreePBX->Config->get("ASTSPOOLDIR");
		$localpath = sprintf('%s/backup/%s/%s',$spooldir,$fileparts[0],$fileparts[1]);
		if(!file_exists($localpath)){
			$this->FreePBX->Filestore->get($parts[0],$parts[1],$file,$localpath);
		}
		if(!file_exists($localpath)){
			return '';
		}
		$this->setConfig(md5($localpath),$localpath,'localfilepaths');

		return $localpath;
	}
	public function determineBackupFileType($filepath){
		$tar = new Tar();
		$tar->open($filepath);
		$files = $tar->contents();
		foreach ($files as $file) {
			if ($file->getIsdir() && $file->getPath() === 'modulejson') {
				return 'current';
			}
		}

		return 'legacy';
	}
}
