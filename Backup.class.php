<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 */
namespace FreePBX\modules;
use FreePBX\modules\Backup\Handlers as Handler;
use FreePBX\modules\Filestore\Modules\Remote as FilestoreRemote;
use FreePBX\modules\Backup\Models\BackupSplFileInfo;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Monolog\Handler\SwiftMailerHandler;
use Monolog\Handler\BufferHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FreePBX_Helpers;
use BMO;
use splitbrain\PHPArchive\Tar;
use FreePBX\modules\Backup\Handlers\MonologSwift;
use Hhxsv5\SSE\SSE;
use Hhxsv5\SSE\Event;
use function FreePBX\modules\Backup\Json\json_decode;
use function FreePBX\modules\Backup\Json\json_encode;
include __DIR__.'/vendor/autoload.php';
#[\AllowDynamicProperties]
class Backup extends FreePBX_Helpers implements BMO {
	public $swiftmsg = false;
	public $backupHandler  = null;
	public $restoreHandler = null;
	public $errors = [];
	public $templateFields = [];
	public $backupFields = [
		'backup_name',
		'backup_description',
		'backup_items',
		'backup_storage',
		'backup_schedule',
		'schedule_enabled',
		'maintage',
		'maintruns',
		'backup_email',
		'backup_emailtype',
		'backup_emailinline',
		'backup_addbjname',
		'immortal',
		'warmspareenabled',
		'warmspare_remotenat',
		'warmspare_cert',
		'warmspare_remotebind',
		'warmspare_remotenat',
		'warmspare_remotedns',
		'warmspare_remoteapply',
		'warmspare_remoteip',
		'warmspare_user',
		'publickey',
		'warmsparewayofrestore',
		'warmspare_remoteapi_filestoreid',
		'warmspare_remoteapi_accesstoken',
		'warmspare_remoteapi_accesstokenurl',
		'warmspare_remoteapi_accesstoken_expire',
		'warmspare_remoteapi_clientid',
		'warmspare_remoteapi_secret',
		'warmspare_remoteapi_gql',
		'warmspare_excludetrunks',
		'warmspare_remotessh_filestoreid',
		'custom_files',
		'prebu_hook',
		'postbu_hook',
		'prere_hook',
		'postre_hook',
		'core_disabletrunks'
	];
	public $loggingHooks = null;

	private ?array $validModulesCache = null;


	public function __construct($freepbx = null) {
		if ($freepbx == null) {
				throw new Exception('Not given a FreePBX Object');
		}
		$this->freepbx = $freepbx;
		$this->db = $freepbx->Database;
	}

	public function __get($var) {
		switch($var) {
			case 'serverName':
				$this->serverName = $this->freepbx->Config->get('FREEPBX_SYSTEM_IDENT');
				return $this->serverName;
			break;
			case 'fs':
				$this->fs = new Filesystem;
				return $this->fs;
			break;
			case 'mf':
				$this->mf = \module_functions::create();
				return $this->mf;
			break;
		}
	}
	/* Generate ecdsa key */
	public function generatekey($delete =false) {
		$homedir = $this->getAsteriskUserHomeDir();
		//.ssh check directory exist or not
		if(!file_exists($homedir.'/.ssh')) {
			$cmd = "mkdir ".$homedir."/.ssh";
			shell_exec($cmd);
		}
		// authorized_keys check file exists or not
		if( !file_exists($homedir.'/.ssh/authorized_keys')){
			$cmd = "touch ".$homedir.'/.ssh/authorized_keys';
			shell_exec($cmd);
			$cmd = "chmod 600 ".$homedir.'/.ssh/authorized_keys';
			shell_exec($cmd);
			$cmd = "chown asterisk:asterisk ".$homedir.'/.ssh/authorized_keys';
                        shell_exec($cmd);
		}
		$keyFilePath = $homedir.'/.ssh/id_ecdsa';
		if($delete == true) {
			$cmd = "rm  ".$keyFilePath;
			shell_exec($cmd);
			$cmd = "rm  ".$homedir.'/.ssh/id_ecdsa.pub';
			shell_exec($cmd);
		}
		if (!file_exists($keyFilePath)) {
			$command = 'ssh-keygen -t ecdsa -b 521 -f ' . escapeshellarg($keyFilePath) . ' -N ""';
			$output = shell_exec($command);
			$cmd = "chmod 600 /home/asterisk/.ssh/id_ecdsa";
			shell_exec($cmd);
			$cmd = "chown asterisk:asterisk /home/asterisk/.ssh/id_ecdsa";
			shell_exec($cmd);
			out(_("SSH key Generated"));
		} else {
			out(_("The SSH key already exists"));
		}
	}
	/* key health is good or not "ecdsa-sha2-nistp521" */
	private function checkKeyhealth() {
		// the public is start with ecdsa-sha2-nistp521  then it is good
		$homedir = $this->getAsteriskUserHomeDir();
		$filePath = $homedir.'/.ssh/id_ecdsa.pub';
		if (!file_exists($filePath)) {
			return true;
		}
		$fileContents = file_get_contents($filePath);
		if ($fileContents === false) {
			return false;
		}
		$startsWith = 'ecdsa-sha2-nistp521';
		if (strpos($fileContents, $startsWith) === 0) {
			return false;
		}
		return false;
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
			$servers = new Backup\Migration\Servers($this->freepbx);
			$mapping = $servers->process();

			out(_("Migrating legacy backups to the new backup"));
			$jobs = new Backup\Migration\Backupjobs($this->freepbx);
			$jobs->process($mapping);

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
				$this->db->query("DROP TABLE $table");
			}
          
          	$tmp = $this->freepbx->Config->get("ASTSPOOLDIR");
			if(file_exists($tmp."/backup.log")){
				unlink($tmp."/backup.log");
			}
          
			$crons = $this->freepbx->Cron->getAll();
			foreach($crons as $c) {
				if(preg_match('/backup\.php/',(string) $c,$matches)) {
					$this->freepbx->Cron->remove($c);
				}
			}
		}
	}

	public function uninstall(){
	}

	public function doConfigPageInit($page) {
		if($page == 'backup'){
			/** Delete Backup */
			if(isset($_REQUEST['action']) && (($_REQUEST['action'] == 'delete') || ($_REQUEST['action'] == 'del'))) {
				return $this->deleteBackup($_REQUEST['id']);
			}
			/** Update Backup */
			if(isset($_POST['backup_name'])){
				return $this->updateBackup();
			}
		}
	}


	public function getActionBar($request) {
		/** No buttons unless we are in a view */
		if(!isset($request['view'])){
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
				'runcdr' => [
					'name'  => 'runrestorecdr',
					'id'    => 'runrestorecdr',
					'value' => _("Run Restore & Legacy CDR ")
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
		// ** Allow remote consultation with Postman **
		// ********************************************
		// $setting['authenticate'] = false;
		// $setting['allowremote'] = true;
		// return true;
		// ********************************************
		switch ($command) {
			case 'deleteMultipleRestores':
			case 'backupGrid':
			case 'backupItems':
			case 'backupStorage':
			case 'runBackup':
			case 'runRestore':
			case 'remotedownload':
			case 'deleteRemote':
			case 'localdownload':
			case 'localRestoreFiles':
			case 'restoreFiles':
			case 'uploadrestore':
			case 'generateRSA':
			case 'deleteLocal':
			case 'getRestoreLog':
			case 'deleteBackup':
			case 'accesstoken':
			case 'checkchansip':
			case 'publicKeyRemove':
			case 'publicKeySave':
				return true;
			case 'restorestatus':
			case 'backupstatus':
				$setting['changesession'] = false;
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
			case 'accesstoken':
				return $this->GraphQL_Access_token($_REQUEST);
				break;
			case 'deleteMultipleRestores':
				$type = $_REQUEST['type'];
				$files = $_REQUEST['files'];
				$deletes = [];
				switch($type) {
					case 'localrestorefiles':
						foreach($files as $f) {
							$filepath = $this->pathFromId($f['id']);
							if(!$filepath){
								return ['status' => false, "message" => _("Invalid ID Provided")];
							}
							$file = new \SplFileObject($filepath);
							if(!$file->isWritable()){
								return ['status' => false, "message" => _("We don't have permissions to this file")];
							}
							if(!unlink($filepath)){
								return ['status' => false, "message" => _("We can't seem to delete the chosen file")];
							}
							$deletes[] = $f['id'];
						}
						return ['status' => true, 'ids' => $deletes];
					break;
					case 'restoreFiles':
						foreach($files as $f) {
							$server = $f['id'];
							$file = $f['file'];
							$server = explode('_', (string) $server);
							if(!$this->deleteRemote($server[1], $file)){
								return ['status' => false, "message" => _("Something failed, The file may need to be removed manually.")];
							}
							$deletes[] = $f['id'];
						}
						return ['status' => true, 'ids' => $deletes];
					break;
					default:
						return ['status' => false, "message" => "Unknown type $type"];
					break;
				}
			break;
			case 'deleteBackup':
				$id = $_REQUEST['id'];
				if($this->deleteBackup($id)) {
					return ['status' => true, "message" => _("Backup Deleted")];
				}
				return ['status' => false, "message" => _("Something failed.")];
			break;
			case 'deleteRemote':
				$server = $id = $_REQUEST['id'];
				$file = $_REQUEST['file'];
				$server = explode('_', (string) $server);
				if($this->deleteRemote($server[1], $file)){
					return ['status' => true, "message" => _("File Deleted"), "id" => $id];
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
				$homedir = $this->getAsteriskUserHomeDir();
				//$ssh = new FilestoreRemote();
				//$ret = $ssh->generateKey($homedir.'/.ssh');
				$ret = true;
			return ['status' => $ret];
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
				$spooldir = $this->freepbx->Config->get("ASTSPOOLDIR");
				$path = sprintf('%s/backup/uploads', $spooldir);
  				if(!file_exists($path)){
					mkdir($path);
				}
				$finalname = $path.'/'. $_FILES['file']['name'];
  				if(file_exists($finalname)){
					unlink($finalname);
				}
				$uuid_folders = array_diff(scandir($path), ['..', '.']);
				foreach($uuid_folders as $target){
					if(is_dir($path."/".$target)){
						$uuid_content = array_diff(scandir($path."/".$target), ['..', '.']);
						if(empty($uuid_content)){
							@rmdir($path."/".$target);
						}
					}
				}
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
						$buff = fread($file, 2_097_152);
						fclose($file);

						$final = fopen($finalname, 'ab');
						$write = fwrite($final, $buff);
						fclose($final);
						unlink($target_file . $i);
					}
					$filemd5 = md5($finalname);
					$this->setConfig($filemd5, $finalname, 'localfilepaths');
					header("HTTP/1.1 200 Ok");
					return ['status' => true, 'md5' => $filemd5];
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
				$ruid = $_GET['fileid'];
				$legacycdrenable = isset($_REQUEST['legacycdrenable'])?1:0;
				if(isset($_GET['filepath']) && $_GET['filepath']) {
					//filestore
					$parts = explode("_",(string) $_GET['fileid']);
					$info = $this->freepbx->Filestore->getItemById($parts[1]);
					if(empty($info)) {
						return ['status' => false, 'message' => _("Could not find a file for the id supplied")];
					} else {
						$args = '--filestore='.escapeshellarg($parts[1]).' --restore='.escapeshellarg((string) $_GET['filepath']);
					}
				} else {
					//local
					$file = $this->pathFromId($ruid);
					if(!$file){
						return ['status' => false, 'message' => _("Could not find a file for the id supplied")];
					}
					$args = '--restore='.escapeshellarg((string) $file);
				}
				if($legacycdrenable == 1) {
					$args = $args. ' --restorelegacycdr';
				}

				if (isset($_REQUEST['skipchansip'])) {
					switch($_REQUEST['skipchansip']) {
					case 'skipall':
						$args = $args. ' --skipchansipexts --skipchansiptrunks';
						break;
					case 'convertall':
						$args = $args. ' --convertchansipexts2pjsip --convertchansiptrunks2pjsip';
						break;
					case 'skiptrunk_convertextension':
						$args = $args. ' --convertchansipexts2pjsip --skipchansiptrunks';
						break;
					case 'skipextension_converttrunk':
						$args = $args. ' --convertchansiptrunks2pjsip --skipchansipexts';
						break;
					case 'convertextension':
						$args = $args. ' --convertchansipexts2pjsip';
						break;
					case 'skipextension':
						$args = $args. ' --skipchansipexts';
						break;
					case 'skiptrunk':
						$args = $args. ' --skipchansiptrunks';
						break;
					case 'converttrunk':
						$args = $args. ' --convertchansiptrunks2pjsip';
						break;
					default:
						break;
					}
				}

				$jobid   = $this->generateId();
				$location = $this->freepbx->Config->get('ASTLOGDIR');
				$command = $this->freepbx->Config->get('AMPSBIN').'/fwconsole backup '.$args.' --transaction='.escapeshellarg($jobid);
				file_put_contents($location.'/restore_'.$jobid.'_out.log','Running with: '.$command.PHP_EOL);
				$process = \freepbx_get_process_obj($command.' >> '.$location.'/restore_'.$jobid.'_out.log 2> '.$location.'/restore_'.$jobid.'_err.log & echo $!');
				$process->mustRun();
				$log = file_get_contents($location.'/restore_'.$jobid.'_out.log');
				return ['status' => true, 'message' => _("Restore running"), 'transaction' => $jobid, 'restoreid' => $ruid, 'pid' => trim($process->getOutput() ?? ""), 'log' => $log];
			case 'runBackup':
				if(!isset($_GET['id'])){
					return ['status' => false, 'message' => _("No backup id provided")];
				}
				$buid    = $_GET['id'];
				$jobid   = $this->generateId();
				$location = $this->freepbx->Config->get('ASTLOGDIR');
				$warmspare = $this->getConfig('warmspareenabled', $buid) === 'yes';
				if($warmspare){
					$warm = ' --warmspare';
				} else {
					$warm = '';
				}
				$command = $this->freepbx->Config->get('AMPSBIN').'/fwconsole backup --backup=' . escapeshellarg((string) $buid) . '' . $warm . ' --transaction=' . escapeshellarg($jobid) . ' >> '.$location.'/backup_'.$jobid.'_out.log 2> '.$location.'/backup_'.$jobid.'_err.log & echo $!';
				file_put_contents($location.'/backup_'.$jobid.'_out.log','Running with: '.$command.PHP_EOL);
				$process = \freepbx_get_process_obj($command);
				$process->mustRun();
				$log = file_get_contents($location.'/backup_'.$jobid.'_out.log');
				return ['status' => true, 'message' => _("Backup running"), 'transaction' => $jobid, 'backupid' => $buid, 'pid' => trim($process->getOutput() ?? ""), 'log' => $log];
			case 'backupGrid':
				return array_values($this->listBackups());
			case 'backupStorage':
				$storage_ids = [];
				if(isset($_GET['id']) && !empty($_GET['id'])){
					$storage_ids = $this->getStorageByID($_GET['id']);
				}
				try {
					$fstype = $this->getFSType();
					$items  = $this->freepbx->Filestore->listLocations($fstype);
					$return = [];
					foreach ($items['locations'] as $driver => $locations ) {
						$optgroup = [
							'label'    => $driver,
							'children' => []
						];
						foreach ($locations as $location) {
							$name = $location['displayname'] ?? $location ['name'];
							$select       = in_array($driver.'_'.$location['id'], $storage_ids);
							$optgroup['children'][] = [
								'label'    => $name,
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
			case 'backupItems':
				$id  = $_GET['id'] ?? '';
				return $this->moduleItemsByBackupID($id);
			case 'checkchansip':
				if($_REQUEST['type'] == 'local'){
					$fileid = $_REQUEST['fileid'];
					$path = $this->pathFromId($_REQUEST['fileid']);
				}
				if($_REQUEST['type'] == 'remote'){
					$path = $this->remoteToLocal($_REQUEST['fileid'],$_REQUEST['filepath']);
					$fileid = md5((string) $path);
				}
				if(empty($path)){
					return ['status' => false, "message" => "file path not found"];
				}
				$fileClass = new BackupSplFileInfo($path);
				$manifest = $fileClass->getMetadata();
				$res['chansipexists'] = $manifest['chansipexists'] ?? false;
				$res['chansipTrunkExists'] = $manifest['chansipTrunkExists'] ?? false;
				$res['status'] = true;
				return $res;
				break;
			case 'publicKeyRemove':
				$res=[];
				$res['status'] = false;
				try {
					$keyToRemove=$_POST['keyToRemove'] ?? '';
					$data = $this->getAll('publickeyAsteriskUser');
					$publicKeys = $data['publickeyAsteriskUser']['publickeys'] ?? [];
					// Normalize and remove the specific key
					$normalizedKeyToRemove = trim($keyToRemove);
					$transformedArray = array_filter($publicKeys, function ($key) use ($normalizedKeyToRemove) {
						return trim($key['publickeyAsteriskUser']) !== $normalizedKeyToRemove;
					});
					$this->removePublicKey($keyToRemove);
					$this->freepbx->Backup->setConfig('publickeyAsteriskUser', ["publickeys" => array_values($transformedArray)], 'publickeyAsteriskUser');
					$res['status'] = true;
					return $res;
				} catch (\Exception $e) {
					$res['status'] = false;
					$res['message'] = $e->getMessage();
				} finally {
					return $res;
				}
				break;
			case 'publicKeySave':
				$res=[];
				$res['status'] = false;
				try {
					$publickeyAsteriskUser = $_POST['publickeyAsteriskUser'] ?? '';
					$servername = $_POST['servername'] ?? '';
					$data = $this->getAll('publickeyAsteriskUser');
					$publicKeys = $data['publickeyAsteriskUser']['publickeys'] ?? [];
					$keyalreadyExist=false;
					foreach($publicKeys as $value) {
						if (trim($value['publickeyAsteriskUser']) == trim($publickeyAsteriskUser)) {
							$keyalreadyExist=true;
						}
					}
					if ($keyalreadyExist) {
						$res['status'] = false;
						$res['message'] = 'Public key already added';
						return $res;
					}
					$publicKeys[] = ["servername" => $servername, "publickeyAsteriskUser" => $publickeyAsteriskUser];
					$this->appendPublicKey($publickeyAsteriskUser);
					$this->freepbx->Backup->setConfig('publickeyAsteriskUser', ["publickeys" => $publicKeys], 'publickeyAsteriskUser');
					$res['status'] = true;
					$res['message'] = 'Public key saved successfully';
					return $res;
				} catch (\Exception $e) {
					$res['status'] = false;
					$res['message'] = $e->getMessage();
				} finally {
					return $res;
				}
				break;
			default:
				return false;
		}
	}
	public function ajaxCustomHandler() {

		switch($_REQUEST['command']){
			case 'restorestatus':
			case 'backupstatus':
				if(function_exists("apache_setenv")) {
					apache_setenv('no-gzip', '1');
				}
				session_write_close();
				header_remove();
				header('Content-Type: text/event-stream');
				header('Cache-Control: no-cache');
				header('Connection: keep-alive');
				header("Access-Control-Allow-Origin: *");
				header('Access-Control-Allow-Credentials: true');
				header('X-Accel-Buffering: no');//Nginx: unbuffered responses suitable for Comet and HTTP streaming applications
				$location = $this->freepbx->Config->get('ASTLOGDIR');
				$callback = function () use ($location) {
					if (!isset($_GET['id']) || !isset($_GET['transaction']) || !isset($_GET['pid'])) {
						return json_encode(['status' => 'stopped', 'error' => _("Missing id or transaction or pid")]);
					}
					$pid = $_GET['pid'];
					$job = $_GET['transaction'];
					$buid = $_GET['id'];

					$type = $_REQUEST['command'] === 'restorestatus' ? 'restore' : 'backup';

					$outFile = $location . '/' . $type . '_' . $job . '_out.log';
					$errorFile = $location . '/' . $type . '_' . $job . '_err.log';

					if (!file_exists($outFile)) {
						if (posix_getpgid($pid) !== false) {
							return json_encode(['status' => 'errored', 'log' => _("Log file is missing but process is still running!")]);
						} else {
							return json_encode(['status' => 'stopped', 'log' => _("Process is no longer running")]);
						}
					}
					$log = file_get_contents($outFile);

					if (posix_getpgid($pid) !== false) {
						return json_encode(['status' => 'running', 'log' => $log]);
					}

					$error = file_get_contents($errorFile);
					if (!empty($error)) {
						@unlink($outFile);
						@unlink($errorFile);
						return json_encode(['status' => 'errored', 'log' => $log . $error]);
					}

					@unlink($outFile);
					@unlink($errorFile);
					return json_encode(['status' => 'stopped', 'log' => $log]);
				};
				(new SSE(new Event($callback, 'new-msgs')))->start();
				exit;
			break;
			case 'remotedownload':
				$filepath = $this->remoteToLocal($_REQUEST['id'],$_REQUEST['filepath']);
			case 'localdownload':
				if(empty($_REQUEST['id'])){
					return false;
				}
				if(!isset($filepath)){
					$filepath = $this->getAll('localfilepaths');
					$filepath = $filepath[$_REQUEST['id']] ?? false;
				}
				if(empty($filepath)){
					return false;
				}
				header("Content-disposition: attachment; filename=".basename((string) $filepath));
				header("Content-type: application/octet-stream");
				readfile($filepath);
				exit;
		}
	}

	public function getRightNav($request) {
		if (!isset($request['view'])) {
			return false;
		}
		switch($request['view']) {
			case 'addbackup':
			case 'editbackup':
			case 'processrestore':
				return load_view(__DIR__."/views/rnav.php",[]);
			break;
		}
	}
public function GraphQL_Access_token($request) {
		$res = [];
  $client_id = $request['warmspare_remoteapi_clientid'];
		$client_secret = $request['warmspare_remoteapi_secret'];
		$token_url = $request['warmspare_remoteapi_accesstokenurl'];
		$content = ["grant_type"=>"client_credentials", "scope"=>"gql:backup:write"];
		$authorization = base64_encode("$client_id:$client_secret");
		$header = ["Authorization: Basic {$authorization}", "Content-Type: application/x-www-form-urlencoded"];
		$pest = new \Pest($token_url);
		$pest->setupAuth($client_id,$client_secret);
		$response = $pest->post($token_url, $content, $header);
		$token = json_decode($response)->access_token;
		$expires_in = json_decode($response)->expires_in;
		$token_type = json_decode($response)->token_type;
		$expires_in = time() + $expires_in;
		if (!empty($token)) {
			$res['access_token'] = $token;
			$res['token_type'] = $token_type;
			$res['expires_in'] = $expires_in;
			$retrun = json_encode($res);
		} else {
			$retrun = '';
		}
		return $retrun;
	}
	public function triggerWarmSpareGqlAPI($item , $filename,$transactionid,$sparefilepath) {
		$sparefilepath = rtrim((string) $sparefilepath,'/');
		if ($item['backup_addbjname'] == 'yes') {
			$foldername = $item['backup_name'];
			$filename = $sparefilepath.'/'.$foldername.'/'.$filename;
		} else {
			$filename = $sparefilepath.'/'.$filename;
		}
		//get new token if access_token is expired !!
		if($item['warmspare_remoteapi_accesstoken_expire'] < time()) {
			$jsonarray = $this->GraphQL_Access_token($item);
			$array = json_decode($jsonarray,true);
			$item['warmspare_remoteapi_accesstoken'] = $array['access_token'];
		}
		$service_url = $item['warmspare_remoteapi_gql'];
		$access_token = $item['warmspare_remoteapi_accesstoken'];
		$client = new \EUAutomation\GraphQL\Client($service_url);
		$query = 'mutation{runWarmsparebackuprestore(input:{backupfilename:"'.$filename.'" clientMutationId:"'.$transactionid.'"}) {clientMutationId restorestatus}}';
		$headers = ["Authorization"=> "Bearer {$access_token}", "Content-Type"=> "application/json"];
		$variables = '';
		$response = $client->json($query, $variables, $headers);
		return $response;
	}
	
	public function RunRestoreusingSSH($item , $filename,$transactionid) {
		$path = null;
  $return = [];
  //get SSH details from filestore
		$filestoteid = substr((string) $item['warmspare_remotessh_filestoreid'],4);
		$filestore = $this->freepbx->Filestore->getItemById($filestoteid);
		$key = $filestore['key'];
		$user = $filestore['user'];
		$host = $filestore['host'];
		$sparefilepath = $filestore['path'];
		$sparefilepath = rtrim((string) $sparefilepath,'/');
		if ($item['backup_addbjname'] == 'yes') {
			$foldername = $item['backup_name'];
			$filename = $sparefilepath.'/'.$foldername.'/'.$filename;
		} else {
			$filename = $sparefilepath.'/'.$filename;
		}
		$command = "ssh -tt -i $key -o StrictHostKeyChecking=no $user@$host '/usr/sbin/fwconsole backup --restore $path$filename --transaction=$transactionid'";
		$process = \freepbx_get_process_obj($command);
		try {
			$process->setTimeout(null);
			$process->mustRun();
			$return['status'] = true;
			$return['msg']= _('Backup Restored Successfully');
		} catch (ProcessFailedException) {
			$return['msg']= _('Error running Restore on Spare Server');
			$return['status'] = false;
		}
		return $return;
	}

	//Display stuff

	public function myShowPage() {
		$view = !empty($_GET['view']) ? $_GET['view'] : '';
		switch($view) {
			case 'editbackup':
				$backup = $this->getBackup($_GET['id']);
				if(empty($backup)) {
					return _("Invalid Backup ID");
				}
			case 'addbackup':
				$randcron          = sprintf('59 23 * * %s',random_int(0,6));
				$vars              = ['id' => ''];
				$vars['backup_schedule'] = $randcron;
				if(isset($backup)){
					$vars              = $backup;
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
					$settings = $settings ?: [];
					foreach($warmsparedefaults as $key => $value){
						$value = $settings[$key] ?? $value;
						$vars[$key]  = $value;
					}
					try {
						$fstype = $this->getFSType();
						$items  = $this->freepbx->Filestore->listLocations($fstype);
						$return = [];
						foreach ($items['locations'] as $driver => $locations ) {
							if ($driver != 'FTP' && $driver != 'SSH') {
								continue;
							}
							foreach ($locations as $location) {
								$name = $location['displayname'] ?? $location ['name'];
								$select = (!empty($vars['warmspare_remoteapi_filestoreid']) && ($driver.'_'.$location['id']== $vars['warmspare_remoteapi_filestoreid']))? true : '';
								$optgroup[] = [
									'label'    => $name,
									'value'    => $driver.'_'.$location['id'],
									'selected' => $select
								];
							}
							if ($driver != 'SSH') {
								continue;
							}
							foreach ($locations as $location) {
								$name = $location['displayname'] ?? $location ['name'];
								$select = (!empty($vars['warmspare_remotessh_filestoreid']) && ($driver.'_'.$location['id']== $vars['warmspare_remotessh_filestoreid']))? true : '';
								$sshoptgroup[] = [
									'label'    => $name,
									'value'    => $driver.'_'.$location['id'],
									'selected' => $select
								];
							}
						}
						$vars['filestores'] = (isset($optgroup) && is_array($optgroup)) ? $optgroup : [];
						$vars['filestoressh'] = (isset($sshoptgroup) && is_array($sshoptgroup)) ? $sshoptgroup : [];
					} catch (\Exception) {
						$vars['filestores'] = false;
					}
					
					$vars['warmspare'] = load_view(__DIR__.'/views/backup/warmspare.php',$vars);
				}
				$vars['transfer'] = '';
				if (isset($transferdisabled) && !$transferdisabled) {
					$vars['transfer'] = '<li role="presentation" class="'.(isset($_GET['view']) && $_GET['view'] == 'yes')?"active":"".'"><a href="?display=backup&view=transfer">'. _("System Transfer").'</a></li>';
				}
				return load_view(__DIR__.'/views/backup/form.php',$vars);
			break;
			case 'processrestore':
				$vars['runningRestore'] = null;
				if(!isset($_GET['fileid']) || empty($_GET['fileid'])){
					return load_view(__DIR__.'/views/restore/landing.php',['error' => _("No id was specified to process. Please try submitting your file again.")]);
				}
				if($_GET['type'] == 'local'){
					$fileid = $_GET['fileid'];
					$path = $this->pathFromId($_GET['fileid']);
				}
				if($_GET['type'] == 'remote'){
					$path = $this->remoteToLocal($_GET['fileid'],$_GET['filepath']);
					$fileid = md5((string) $path);
				}
				if(empty($path)){
					return load_view(__DIR__.'/views/restore/landing.php',['error' => _("Couldn't find your file, please try submitting your file again.")]);
				}
				$fileClass = new BackupSplFileInfo($path);
				$manifest = $fileClass->getMetadata();
				$vars['meta']     = $manifest;
				$vars['timestamp']     = $manifest['date'] ?? '';
				$vars['jsondata'] = $this->moduleJSONFromManifest($manifest);
				$vars['id']       = $_GET['id'] ?? '';
				$vars['fileid']   = $fileid ?? '';
				$vars['fileinfo'] = $fileClass ?? '';
				return load_view(__DIR__.'/views/restore/processRestore.php',$vars);
			break;
			default:
				return load_view(__DIR__.'/views/landing.php',[]);
			break;
		}
	}

	public function showPage($page){
		switch ($page) {
			case 'settings':
				$vars = [];
				$hdir = $this->getAsteriskUserHomeDir();
				$file = $hdir.'/.ssh/id_ecdsa';
				$good = $this->checkKeyhealth();
				$this->generatekey($good);
				$filePub = $hdir.'/.ssh/id_ecdsa.pub';
				$data = file_get_contents($filePub);
				$vars['publickey'] = $data;
				$data = $this->getAll('publickeyAsteriskUser');
				$vars['publickeyAsteriskUser'] = $data['publickeyAsteriskUser']['publickeys'] ?? '';
				return load_view(__DIR__.'/views/backup/settings.php',$vars);
			break;
			case 'backup':
				if(isset($_GET['view']) && $_GET['view'] == 'newRSA'){
					return load_view(__DIR__.'/views/backup/rsa.php');
				}
				if(isset($_GET['view']) && $_GET['view'] == 'form'){

				}
				if(isset($_GET['view']) && $_GET['view'] == 'download'){
					return load_view(__DIR__.'/views/backup/download.php');
				}
				if(isset($_GET['view']) && $_GET['view'] == 'transfer'){
					return load_view(__DIR__.'/views/backup/transfer.php');
				}
				$runningList = $this->freepbx->Backup->getAll("runningBackupJobs");
				$runningList = is_array($runningList) ? $runningList : [];
				$finalList = [];
				foreach($runningList as $buid => $info) {
					if(!posix_getpgid($info['pid'])) {
						$this->freepbx->Backup->delConfig($buid,"runningBackupJobs");
						continue;
					}
					$finalList[$buid] = $info;
				}
				return load_view(__DIR__.'/views/backup/grid.php',['runningBackups' => $finalList]);
			case 'restore':
				$view = $_GET['view'] ?? 'default';
				$running = $this->freepbx->Backup->getConfig("runningRestoreJob");
				if(empty($running) || !posix_getpgid($running['pid'])) {
					if(!empty($running) && !posix_getpgid($running['pid'])) {
						$this->freepbx->Backup->delConfig("runningRestoreJob");
					}
					return load_view(__DIR__.'/views/restore/landing.php');
				} else {
					$path = $this->pathFromId($running['fileid']);
					if(empty($path)){
						return load_view(__DIR__.'/views/restore/landing.php',['error' => _("Couldn't find your file, please try submitting your file again.")]);
					}
					$fileClass = new BackupSplFileInfo($path);
					$manifest = $fileClass->getMetadata();
					$vars['meta']     = $manifest;
					$vars['timestamp']     = $manifest['date'];
					$vars['jsondata'] = $this->moduleJSONFromManifest($manifest);
					$vars['id']       = $_GET['id'] ?? '';
					$vars['fileid']   = $fileid ?? '';
					$vars['fileinfo'] = $fileClass ?? '';
					$vars['runningRestore'] = $running ?? '';
					return load_view(__DIR__.'/views/restore/processRestore.php',$vars);
				}


			default:
				return load_view(__DIR__.'/views/backup/grid.php');
		}
	}

	public function getBackupSettingsDisplay($id = ''){
		$modules = $this->freepbx->Hooks->processHooks($id);
		foreach($modules as $module => &$data) {
			$data = '<form id="modulesetting_'.strtolower((string) $module).'">'. $data.'</form>';
		}
		return $modules;
	}

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
		$homedir = $this->getAsteriskUserHomeDir();
		$hookpath      = $hookpath ?: $homedir.'/Backup';

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

	/**
	 * Gets the appropriate filesystem types to pass to filestore.
	 * @return mixed if hooks are present it will present an array, otherwise a string
	 */
	public function getFSType(){
		$types = $this->freepbx->Hooks->processHooks();
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
		if(empty($data)) {
			return [];
		}
		$return = [];
		foreach ($this->backupFields as $key) {
			$return[$key] = $data[$key] ?? '';
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
		$base      = $this->freepbx->Config->get('ASTSPOOLDIR');
		$base      = $base ?: '/var/spool/asterisk';
		$backupdir = $base . '/backup';

		$this->fs->mkdir($backupdir);
		//  find all runningBackupstatus
		$runningBackupstatus = $this->getAll('runningBackupstatus');
		$localfiles = [];
		foreach( $runningBackupstatus as $backs) {
			if($backs['status'] == 'FINISHED'){
				if(file_exists($backs['backupfile'])){
					//local file exits here 
					$localfiles[$backs['backupfile']] = $backs;
				}else { //missed files
					$filepath   = preg_replace('#/backup/#', '/', $backs['backupfile'], 1);
					if(file_exists($filepath)){
						$backs['backupfile'] = $filepath;
						$localfiles[$backs['backupfile']] = $backs;
					}
				}
			}
		}
		$Directory = new \RecursiveDirectoryIterator($backupdir,\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::CURRENT_AS_FILEINFO);
		$Iterator  = new \RecursiveIteratorIterator($Directory,\RecursiveIteratorIterator::LEAVES_ONLY);
		$this->delById('localfilepaths');
		foreach($Iterator as $k => $v){
			$path       = $v->getPathInfo()->getRealPath();
			$buname     = $v->getFilename();
			$buname     = str_replace('_',' ',(string) $buname);
			$backupFile = new BackupSplFileInfo($k);
			$backupinfo = $backupFile->backupData();
			if(empty($backupinfo)){
				continue;
			}
			unset($localfiles[$k]);
			$this->setConfig(md5((string) $k),$k,'localfilepaths');
			$backupinfo['path'] = $path;
			$backupinfo['id']   = md5((string) $k);
			$backupinfo['name'] = $buname;
			$backupinfo['timestamp'] = $backupinfo['timestamp'];
			$backupinfo['size'] = $backupinfo['size'];
			$files     []       = $backupinfo;
		}
		foreach ($localfiles as $f) {
			$file = $f['backupfile'];
			$path       = dirname($file);
			$buname     = $this->getConfig('backup_name',$f['buid']);
			$buname     = str_replace('_',' ',(string) $buname);
			$backupFile = new BackupSplFileInfo($file);
			$backupinfo = $backupFile->backupData();
			if(empty($backupinfo)){
					continue;
			}
			$this->setConfig(md5((string)$file),$file,'localfilepaths');
			$backupinfo['path'] = $path;
			$backupinfo['id']   = md5((string) $file);
			$backupinfo['name'] = $buname;
			$backupinfo['timestamp'] = $backupinfo['timestamp'];
			$files[]     = $backupinfo;
		}
		usort($files, function($a, $b) {
			return $b['timestamp'] - $a['timestamp'];
		});
		return $files;
	}

	/**
	 * Get a list of modules that implement the backup method
	 * @return array list of modules
	 */
	public function getModules(){
		if($this->validModulesCache) {
			return $this->validModulesCache;
		}
		//All modules impliment the "backup" method so it is a horrible way to know
		//which modules are valid. With the autoloader we can do this magic :)
		$webrootpath = \FreePBX::Config()->get('AMPWEBROOT');
		$moduleInfo = \FreePBX::Modules()->getInfo(false,MODULE_STATUS_ENABLED);
		$validmods = [];
		foreach ($moduleInfo as $rawname => $data) {
			if($rawname === 'framework') {
				$validmods[$rawname] = $data;
				continue;
			}
			$bufile = $webrootpath . '/admin/modules/' . $rawname.'/Backup.php';
			if(file_exists($bufile)){
				$validmods[$rawname] = $data;
			}
		}

		$this->validModulesCache = $validmods;

		return $validmods;
	}

	/**
	 * Get modules for a specific backup id returned in an array
	 * @param  string  $id              The backup id
	 * @return array   list of module data
	 */
	public function moduleItemsByBackupID($id = ''){

		$settingdisplays = $this->getBackupSettingsDisplay($id);

		$modules  = $this->getModules();
		if(!empty($id)) {
			$selected = $this->getAll('modules_'.$id);
			$selected = is_array($selected)? array_keys($selected) :[];
		} else {
			$selected = [];
		}

		$ret = [];
		foreach ($modules as $module) {
			$item = [
				'modulename' => $module['rawname'],
				'selected'   => empty($id) || in_array($module['rawname'], $selected),
				'display' => $module['name']
			];
			if(isset($settingdisplays[ucfirst(strtolower((string) $module['rawname']))])) {
				$item['settingdisplay'] = $settingdisplays[ucfirst(strtolower((string) $module['rawname']))];
			}
			$ret[] = $item;
		}
		return $ret;
	}


	//Setters
	public function scheduleJobs($id = 'all'){
		$sbin = $this->freepbx->Config->get('AMPSBIN');
		if($id !== 'all'){
			$enabled = $this->getBackupSetting($id, 'schedule_enabled');
			$warmspare = $this->getConfig('warmspareenabled', $id) === 'yes';
			if($enabled === 'yes'){
				$schedule = $this->getBackupSetting($id, 'backup_schedule');
				$command  = sprintf($sbin.'/fwconsole backup --backup=%s %s > /dev/null 2>&1',$id, $warmspare ? '--warmspare' : '');
				$backupOptionWithId  = '--backup=' . $id;
				$this->freepbx->Cron->removeAll($backupOptionWithId);
				$this->freepbx->Cron->add($schedule.' '.$command);
				return true;
			}
		}
		//Clean slate
		$allcrons = $this->freepbx->Cron->getAll();
		$allcrons = is_array($allcrons)?$allcrons:[];
		foreach ($allcrons as $cmd) {
			if (str_contains((string) $cmd, 'fwconsole backup')) {
				$this->freepbx->Cron->remove($cmd);
			}
		}
		$backups = $this->listBackups();
		foreach ($backups as $key => $value) {
			$enabled = $this->getBackupSetting($key, 'schedule_enabled');
			$warmspare = $this->getConfig('warmspareenabled', $key) === 'yes';
			if($enabled === 'yes'){
				$schedule = $this->getBackupSetting($key, 'backup_schedule');
				$command  = sprintf($sbin.'/fwconsole backup --backup=%s %s> /dev/null 2>&1',$key, $warmspare ? '--warmspare' : '');
				$backupOptionWithId  = '--backup=' . $id;
				$this->freepbx->Cron->removeAll($backupOptionWithId);
				$this->freepbx->Cron->add($schedule.' '.$command);
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
			if($col == 'backup_name'){
				$value = str_replace(' ', '-', (string) $value); 
				$value = preg_replace('/[^A-Za-z0-9\-]/', '', $value);
			}
			if($col == 'core_disabletrunks') {
				$disableTrunkValue = $this->freepbx->Core->getConfig('core_disabletrunks', $data['id']);
				$value = (!empty($disableTrunkValue) ? $disableTrunkValue : 'no');
			}
			$this->updateBackupSetting($data['id'], $col, $value);
		}

		$backup_name = $this->getReq('backup_name','');
		$backup_name = str_replace(' ', '-', (string) $backup_name); 
		$backup_name = preg_replace('/[^A-Za-z0-9\-]/', '', $backup_name);
		$description = $this->getReq('backup_description',sprintf(_('Backup %s'),$backup_name));
		$data['backup_items'] = $this->getReqUnsafe('backup_items', 'unchanged');
		$backup_items = json_decode(html_entity_decode((string) $this->getReq('backup_items',[])),true);
		$cftype = $this->getReq('type');
		$path = $this->getReq('path');
		$exclude = $this->getReq('exclude');

		return $this->performBackup($data,$backup_name,$description,$backup_items,$cftype,$path,$exclude);
	}

	/**
	 * Update a backup item from GQL. Note the only difference is weather we generate an ID
	 * @param  array $data an array of the items needed.
	 * @return string the backup id
	 */
	public function updateGQLBackup($input)
	{
		$data = [];
		$data['id'] = $input['id'];
		foreach ($this->backupFields as $col) {
			//This will be set independently
			if ($col == 'immortal') {
				continue;
			}
			if (array_key_exists($col, $input)) {
				$value = $input[$col];
				if ($col == 'backup_name') {
					$value = str_replace(' ', '-', (string) $value);
					$value = preg_replace('/[^A-Za-z0-9\-]/', '', $value);
				}
				if ($col == 'backup_items') {
					$value = json_encode($value);
				}
				$this->updateBackupSetting($data['id'], $col, $value);
			}
		}
		$backup_name = $input['backup_name'];
		$description = $input['backup_description'];
		$data['backup_items'] = $input['backup_items'];
		$backup_items = $input['backup_items'];
		$cftype = $input['type'] ?? '';
		$path = $input['path'] ?? '';
		$exclude = $input['exclude'] ?? '';
		return $this->performBackup($data, $backup_name, $description, $backup_items, $cftype, $path, $exclude);
	}

	/**
  * performBackup
  *
  * @param  mixed $cftyp
  * @return void
  */
 public function performBackup(mixed $data,mixed $backup_name,mixed $description,mixed $backup_items,$cftype,mixed $path,mixed $exclude){
		$values = [];
  //remove all special charaters
		$id = $data['id'];
		$this->setConfig($data['id'],['id' => $data['id'], 'name' => $backup_name, 'description' => $description],'backupList');
		//We expect this to be JSON so we don't sanitize it.
	
		if($data['backup_items'] !== 'unchanged') {
			$processibleSettings = [];

			foreach($backup_items as &$item) {
				if(isset($item['settings'])) {
					$processibleSettings[$item['modulename']] = $item['settings'];
					unset($item['settings']);
				}
			}
			$this->setModulesById($data['id'], $backup_items);
			$this->processBackupSettings($data['id'], $processibleSettings);
		}
		
		$saved = [];
		if (is_array($cftype)) {
			foreach ($cftype as $e_id => $type) {
				if (!isset($saved[$type], $saved[$type][$path[$e_id]])) {
					$saved[$type][$path[$e_id]] = true;
					$excludes = trim((string) $exclude[$e_id]) ? explode("\n", (string) $exclude[$e_id]) : [];
					foreach ($excludes as $my => $e) {
						$excludes[$my] = trim($e);
					}
					$excludes  = array_unique($excludes);
					$values[] = ['type' => $type, 'path'=> $path[$e_id], 'exclude'=> $excludes];
				}
			}
			$customVal = json_encode($values);
			$this->setConfig('custom_files', $customVal, $data['id']);

		}
		$this->scheduleJobs($id);
		return $id;
	}

	public function processBackupSettings($id = '', $data = []){
		$hooks = $this->freepbx->Hooks->returnHooksByClassMethod(\FreePBX\modules\Backup::class, 'processBackupSettings');
		foreach($hooks as $hook) {
			$module = $hook['module'];
			if(empty($data[strtolower((string) $module)])) {
				continue;
			}
			$tmp = [];
			foreach($data[strtolower((string) $module)] as $item) {
				$tmp[$item['name']] = $item['value'];
			}
			$method = $hook['method'];
			$this->freepbx->$module->$method($id, $tmp);
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


	public function setConfig($setting = null, $value = false, $id = 'noid') {
		return parent::setConfig($setting, $value, $id);
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
		$this->freepbx->Hooks->processHooks($id);
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
		if(!is_array($deps)) {
			return $ret;
		}
		foreach($deps as $dep){

			if($this->freepbx->Modules->getInfo(strtolower((string) $dep),true)){
				continue;
			}
			try{
				$this->mf->install(strtolower((string) $dep),true);
			}catch(\Exception){
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
		return \Ramsey\Uuid\Uuid::uuid4()->toString();
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
		$path = \FreePBX::Config()->get($out[1]);
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
			return json_encode([]);
		}
		foreach($data['modules'] as $module){
			$name    = $module['module'];
			$version = $module['version'];
			$status  = ($this->freepbx->Modules->checkStatus(strtolower((string) $name)))?_("Enabled"):_("Uninstalled or Disabled");
			$return[] = [
				'modulename' => $name,
				'version'    => $version,
				'installed'  => $status
			];
		}
		return json_encode($return);
	}

	public function deleteRemote($id, $path){
		return $this->freepbx->Filestore->delete($id, $path);
	}

	public function getAllRemote(){
		$final = [];
		$ret = $this->freepbx->Filestore->listAllFiles(true);
		foreach($ret as $dname => $driver){
			foreach($driver as $id => $location){
				if(!isset($location['results'])){
					continue;
				}
				foreach($location['results'] as $file){
					if($file['type'] == 'dir'){
						continue;
					}
					if(!isset($file['path'])) {
						continue;
					}
					$backupFile = new BackupSplFileInfo($file['path']);
					$info = $backupFile->backupData();
					if($info === false) {
						continue; //not a backup file
					}
					$infoSize = $this->freepbx->Filestore->getSize($id, $file['path']);
					$final[] = [
						'id' => $dname.'_'.$id.'_'.sha1((string) $file['path']),
						'type' => $dname,
						'file' => $file['path'],
						'framework' => $info['framework'],
						'timestamp' => $info['timestamp'],
						'name' => $file['basename'] ?? '',
						'instancename' => $location['name'],
						'size'     => $infoSize,
					];
				}
			}
		}
		usort($final, function($a, $b) {
			return $b['timestamp'] - $a['timestamp'];
		});
		return $final;
	}
	public function remoteToLocal($location,$file){
		$parts = explode('_',(string) $location);
		$info = $this->freepbx->Filestore->getItemById($parts[1]);
		$fileparts = array_slice(explode('/',(string) $file),-2);
		$spooldir = $this->freepbx->Config->get("ASTSPOOLDIR").'/tmp';
		$localpath = sprintf('%s/%s',$spooldir,basename((string) $file));
		if(!file_exists($localpath)){
			$this->freepbx->Filestore->download($parts[1],$file,$localpath);
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
	/**
	 * Returns the home directory of the AMPASTERISKWEBUSER. If the user has no home directory we return home dir for the current running process.
	 *
	 * @return string path to home dir such as /home/asterisk
	 */
	public function getAsteriskUserHomeDir(){
		if(!isset($this->homeDir) || empty($this->homeDir)){
			$webuser = $this->freepbx->Config->get('AMPASTERISKWEBUSER');

			if (!$webuser) {
				throw new \Exception(_("I don't know who I should be running Backup as."));
			}

			// We need to ensure that we can actually read the GPG files.
			$web = posix_getpwnam($webuser);
			if (!$web) {
				throw new \Exception(sprintf(_("I tried to find out about %s, but the system doesn't think that user exists"),$webuser));
			}
			$home = trim((string) $web['dir']);
			if (!is_dir($home)) {
				// Well, that's handy. It doesn't exist. Let's use ASTSPOOLDIR instead, because
				// that should exist and be writable.
				$home = $this->freepbx->Config->get('ASTSPOOLDIR');
				if (!is_dir($home)) {
					// OK, I give up.
					throw new \Exception(sprintf(_("Asterisk home dir (%s) doesn't exist, and, ASTSPOOLDIR doesn't exist. Aborting"),$home));
				}
			}

			$this->homeDir = $home;
		}
		return $this->homeDir;
	}

	/* This method is useful for modules to run somethinng special after all module restore
	* And before httpd restart 
	*/
	public function postrestoreModulehook($transactionid,$backupinfo=[]) {
		 $this->freepbx->Hooks->processHooks($transactionid,$backupinfo);
		return;
	}

	public function backup_template_generate_tr($c, $i, $build_tr = false) {
		$type = '';
		$path = '';
		$exclude = '';

		switch ($i['type']) {
			case 'file':
				$type = _('File') . form_hidden('type[' . $c . ']', 'file');
				$path = ['name' => 'path[' . $c . ']', 'value' => $i['path'], 'required' => '', 'placeholder' => _('/path/to/file')];
				$path = form_input($path);
				$exclude = form_hidden('exclude[' . $c . ']', '');
				break;

			case 'dir':
				$type = _('Directory') . form_hidden('type[' . $c . ']', 'dir');
				$path = ['name' => 'path[' . $c . ']', 'value' => $i['path'], 'required' => '', 'placeholder' => _('/path/to/dir')];
				$path = form_input($path);
				$exclude = ['name' => 'exclude[' . $c . ']', 'value' => implode("\n", $i['exclude']), 'rows' => is_countable($i['exclude']) ? count($i['exclude']) : 0, 'cols' => 20, 'placeholder' => _('PATTERNs, one per line')];
				$exclude = form_textarea($exclude);
				break;
		}
		$del_txt = _('Delete this entry. Don\'t forget to click Submit to save changes!');
		$delete = '<img src="images/trash.png" style="cursor:pointer" title="'. $del_txt . '" class="delete_entrie">';

		if($build_tr) {
			return '<tr><td>'
				. $type . '</td><td>'
				. $path . '</td><td>'
				. $exclude . '</td><td>'
				. $delete . '</td></tr>';
		} else {
			return ['type' => $type, 'path' => $path, 'exclude' => $exclude, 'delete' => $delete];
		}
	}

	public function appendPublicKey($publicKey) {
		$filePath = '/home/asterisk/.ssh/authorized_keys';
		$existingKeys = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		$keyExists = false;
		foreach ($existingKeys as $existingKey) {
			if (trim($existingKey) === trim($publicKey)) {
				$keyExists = true;
				break;
			}
		}
		if (!$keyExists) {
			file_put_contents($filePath, $publicKey . PHP_EOL, FILE_APPEND);
		}
	}

	public function removePublicKey($publicKey) {
		$filePath = '/home/asterisk/.ssh/authorized_keys';
		$fileContent = file_get_contents($filePath);
		$updatedContent = str_replace($publicKey, '', $fileContent);
		$updatedContent = preg_replace("/^\s*[\r\n]/m", '', $updatedContent);
		file_put_contents($filePath, $updatedContent);
	}

}
