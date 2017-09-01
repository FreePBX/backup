<?php
/**
 * Copyright Sangoma Technologies, Inc 2015
 */
namespace FreePBX\modules;
use FreePBX\modules\Backup\Handlers as Handler;
use Symfony\Component\Filesystem\Filesystem;
$setting = array('authenticate' => true, 'allowremote' => false);
class Backup extends \DB_Helper implements \BMO {
	public function __construct($freepbx = null) {
		if ($freepbx == null) {
				throw new Exception('Not given a FreePBX Object');
		}
		$this->FreePBX = $freepbx;
		$this->db = $freepbx->Database;
		$this->fs = new Filesystem;
		$this->backupFields = ['backup_name','backup_description','backup_items','backup_storage','backup_schedule','backup_maintinance'];
		$this->templateFields = [];
	}
	public function showPage($page){
		switch ($page) {
			case 'backup':
				if(isset($_GET['view'])){
					$vars = ['id' => ''];
					if(isset($_GET['id']) && !empty($_GET['id'])){
						$vars = $this->getBackup($_GET['id']);
						$vars['id'] = $_GET['id'];
					}

					return show_view(__DIR__.'/views/backup/form.php',$vars);
				}else{
					return show_view(__DIR__.'/views/backup/grid.php');
				}
			break;
			case 'restore':
			case 'templates':
				return '<h1>PLACEHOLDER</h1>';
			break;
		}
	}

	public function doBackup($id = '',$transactionId = '') {
		if(empty($id)){
			throw new \Exception("Backup id not provided", 500);
		}
		$transactionId = !empty($transactionId)?$transactionId:$this->generateId();
		$this->log($transactionId,_("Running pre backup hooks"));
		$this->preBackupHooks($id, $transactionId);
		$backupInfo = $this->getBackup($id);
		$underscoreName = str_replace(' ', '_', $backupInfo['backup_name']);
		$this->log($transactionId,sprintf(_("Starting backup %s"),$underscoreName));
		$tmpdir = sprintf('%s/backup/%s/',sys_get_temp_dir(),$underscoreName);
		$this->fs->mkdir($tmpdir);
		$spooldir = $this->FreePBX->Config->get("ASTSPOOLDIR");
		$pharname = sprintf('%s/backup/%s/backup-%s.tar',$spooldir,$underscoreName,time());
		$this->log($transactionId,sprintf(_("This backup will be stored locally at %s and is subject to maintinance settings"),$pharname));
		$phar = new \PharData($pharname);
		$storage_ids = $this->getStorageById($id);
		$data = [];
		$dirs = [];
		$files = [];
		$manifest = [];
		$validmods = $this->FreePBX->Modules->getModulesByMethod("backup");
		$backupItems = $this->getAll('modules_'.$id);
		$selectedmods = is_array($backupItems)?array_keys($backupItems):[];
		$errors = [];
		foreach($selectedmods as $mod) {
			$moddata = [
				'modules' => [],
				'date' => time(),
				'backupInfo' => $backupInfo,
			];
			if(in_array($mod, $validmods)){
				$err = sprintf(_("Could not backup module %s, it may not be installed or enabled"),$mod);
				$errors[] = $err;
				$this->log($transactionId,$err);
				continue;
			}
			$manifest['modules'][] = $mod;
			$backup = new Handler\Backup($this->FreePBX);
			\modgettext::push_textdomain(strtolower($mod));
			$this->FreePBX->$mod->backup($backup);
			\modgettext::pop_textdomain();
			$moddata['dirs'] = $backup->getDirs();
			$moddata['files'] = $backup->getFiles();
			$moddata['configs'] = $backup->getConfigs();
			$moddata['dependencies'] = $backup->getDependencies();

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

				$destpath = backup__('files/' . $file['path']);
				$destfile = $destpath . '/' . $file['filename'];

				$dirs[] = $destpath;
				$files[$srcfile] = $destfile;
			}

			$modjson = $tmpdir . 'modulejson/' . $mod . '.json';
			if (!$this->fs->exists(dirname($modjson))) {
				$this->fs->mkdir(dirname($modjson));
			}
			file_put_contents($modjson, json_encode($moddata, JSON_PRETTY_PRINT));
			$files[$modjson] = 'modulejson/' . $mod . '.json';

			$data[$mod] = $moddata;
		}

		foreach ($dirs as $dir) {
			$phar->addEmptyDir($dir);
		}
		$manifestfile = $tmpdir. '/manifest.json';
		file_put_contents($manifestfile, json_encode($manifest, JSON_PRETTY_PRINT));

		/* We already have a list of files, so we'll let Phar add the files in bulk. */
		$phar->buildFromIterator(new \ArrayIterator(array_flip($files)));

		$phar->compress(\Phar::GZ);
		$pathinfo = pathinfo($pharname);
		$remote = sprintf('/backup/%s/%s',$underscoreName,$pathinfo['basename']);
		$this->log($transactionId,_("Saving to selected Filestore locations"));
		foreach ($storage_ids as $location) {
			try {
				$location = explode('_', $location);
				$this->FreePBX->Filestore->put($location[0],$location[1],$pharname,$remote);
				$this->doMaintinance($location[0],$location[1],$underscoreName,$transactionId);
			} catch (\Exception $e) {
				$err = $e->getMessage();
				$this->log($transactionId,$err);
				$errors[] = $err;
			}
		}
		$this->doMaintinance('spool','spool',$underscoreName,$transactionId);
		$this->log($transactionId,_("Cleaning up"));
		$this->fs->remove($pharname);
		$this->fs->remove($tmpdir);
		if(!empty($errors)){
			$this->log($transactionId,_("Backup finished with but with errors"));
			return $errors;
		}
		$this->log($transactionId,_("Running post backup hooks"));
		$this->postBackupHooks($id, $transactionId);
		$this->log($transactionId,_("Backup completed successfully"));
		return true;
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
	public function processBackupSettings($id,$settings){
		 $this->FreePBX->Hooks->processHooks($id,$settings);
	}
	public function getBackupSettings($id){
		 return $this->FreePBX->Hooks->processHooks($id);
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

	// TODO rename function
	public function restoreMagic() {
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
			$modjson = $tmpdir . 'modulejson/' . $mod . '.json';

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

	public function install(){
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
					return $this->getBackuoModulesById($id);
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

	public function getStorageById($id){
		$storage = $this->getConfig('backup_storage',$id);
		return is_array($storage)?$storage:[];
	}

	public function getFSType(){
		$types = $this->FreePBX->Hooks->processHooks();
		$ret=[];
		foreach ($types as $key => $value) {
			$value = is_array($value)?$value:[];
			$ret = array_merge($ret,$value);
		}
		return !empty($ret)?$ret:'backup';
	}

	public function generateId(){
		return \Ramsey\Uuid\Uuid::uuid4()->toString();
	}

	/**
	 * List all backups
	 */
	public function listBackups() {
		$return =  $this->getAll('backupList');
		return is_array($return)?$return:[];
	}

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

	public function updateBackup($data){
		$data['id'] = (isset($data['id']) && !empty($data[id]))?$data['id']:$this->generateID();
		foreach ($this->backupFields as $col) {
			$value = isset($data[$col])?$data[$col]:'';
			$this->setConfig($col,$value,$data['id']);
		}
		$description = isset($data['backup_description'])?$data['backup_description']:sprintf('Backup %s',$data['backup_name']);
		$this->setConfig($data['id'],array('id' => $data['id'], 'name' => $data['backup_name'], 'description' => $description),'backupList');
		if(isset($data['backup_items'])){
			$backup_items = json_decode($data['backup_items'],true);
			$backup_items = is_array($backup_items)?$backup_items:[];
			$this->setModulesById($data['id'], $backup_items);
		}
	}
	public function deleteBackup($id){
		$this->setConfig($id,false,'backupList');
		$this->delById($id);
	}

	public function getBackupModules(){
		$ret = $this->FreePBX->Modules->getModulesByMethod("backup");
	}

	public function getRestoreModules(){
		return $this->FreePBX->Modules->getModulesByMethod("restore");
	}
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

	//TODO: Do we need templates any more?
	public function listTemplates(){
		return $this->getConfig('templateList');
	}

	public function getTemplate($id){
		$data = $this->getAll($id);
		$return = [];
		foreach ($this->templateFields as $key => $value) {
			switch ($key) {
				default:
					$return[$key] = isset($data[$key])?$data[$key]:'';
				break;
			}
		}
		return $return;
	}

	public function updateTemplate($data){
		$data['id'] = (isset($data['id']) && !empty($data[id]))?$data['id']:$this->generateID();
		foreach ($this->templateFields as $col) {
			$value = isset($data[$col])?$data[$col]:'';
			$this->setConfig($col,$value,$data['id']);
		}
		$description = isset($data['template_description'])?$data['template_description']:sprintf('Template %s',$data['template_name']);
		$this->setConfig($data['id'],array('id' => $data['id'], 'name' => $data['template_name'], 'description' => $description),'templateList');
	}

	public function deleteTemplate($id){
		$this->setConfig($id,false,'templateList');
		$this->delById($id);
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

	public function doMaintinance($storagedriver,$storageid,$backupName,$transactionId){
		if($storagedriver == 'spool' && $storageid === 'spool'){
			$this->log($transactionId,_("Performing Local Maintinance"));
			$this->log($transactionId,_("Local Maintinance Complete"));
		return true;
		}
		$this->log($transactionId,sprintf(_("Performing maintinance on %s - %s"),$storagedriver,$storageid));
		$this->log($transactionId,sprintf(_("Maintinance complete on %s - %s"),$storagedriver,$storageid));
	}

	//TODO: Make this do spmething... Maybe kvstore then longpoll in the UI (that it the dream)
	public function log($transactionId = '', $message){
		$this->FreePBX->Hooks->processHooks($transactionId,$message);
		echo sprintf('%s [%s] - %s', date('c'), $transactionId, $message).PHP_EOL;
	}
}
