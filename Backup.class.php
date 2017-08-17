<?php
/**
 * Copyright Sangoma Technologies, Inc 2015
 */
namespace FreePBX\modules;
use FreePBX\modules\Backup\Handlers as Handler;
use Symfony\Component\Filesystem\Filesystem;
$setting = array('authenticate' => true, 'allowremote' => false);
class Backup implements \BMO {
	static backupFields = ['backup_name','backup_description','backup_items','backup_storage','backup_schedule','backup_maintinance'];
	static templateFields = ['backup_name','backup_description','backup_items','backup_storage','backup_schedule','backup_maintinance'];
	public function __construct($freepbx = null) {
		if ($freepbx == null) {
				throw new Exception('Not given a FreePBX Object');
		}
		$this->FreePBX = $freepbx;
		$this->db = $freepbx->Database;
		$this->fs = new Filesystem;
	}
	public function showPage($page){
		switch ($page) {
			case 'backup':
				if(isset($_GET['view'])){
					return show_view(__DIR__.'/views/backup/form.php');
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
	// TODO rename function
	public function backupMagic() {
		$tmpdir = sys_get_temp_dir() . '/backup/';

		$this->fs->mkdir($tmpdir);
		$pharname = \FreePBX::Config()->get("ASTSPOOLDIR") . '/backup-' . time() . '.tar';
		$phar = new \PharData($pharname);

		$data = array();
		$dirs = array();
		$files = array();

		$mods = \FreePBX::Modules()->getModulesByMethod("backup");
		foreach($mods as $mod) {
			$moddata = array();

			$backup = new Handler\Backup($this->FreePBX);

			\modgettext::push_textdomain(strtolower($mod));
			$this->FreePBX->$mod->backupModule($backup);
			\modgettext::pop_textdomain();

			$moddata['dirs'] = $backup->getDirs();
			$moddata['files'] = $backup->getFiles();

			foreach ($moddata['dirs'] as $dir) {
				$dirs[] = backup__('files/' . $dir['path']);
			}

			foreach ($moddata['files'] as $file) {
				$srcpath = Handler\Backup::getPath($file);
				if (empty($srcpath)) {
					/* We couldn't create a valid path.  Skip it. */
					// TODO Fail?  Display warning?
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

		/* We already have a list of files, so we'll let Phar add the files in bulk. */
		$phar->buildFromIterator(new \ArrayIterator(array_flip($files)));

		$phar->compress(\Phar::GZ);
		$this->fs->remove($pharname);
		$this->fs->remove($tmpdir);

		return $data;
	}



	// TODO rename function
	// TODO Use processHooks?
	public function backupSettingsMagic() {
		$settings = '';

		$mods = \FreePBX::Modules()->getModulesByMethod("backupSettings");
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
		foreach (glob(\FreePBX::Config()->get("ASTSPOOLDIR") . '/backup-*.tar.gz') as $restorefile) {
			$pharname = $restorefile;
		}
		$phar = new \PharData($pharname);
		$phar->extractTo($tmpdir);

		$data = array();
		$dirs = array();
		$files = array();

		$mods = \FreePBX::Modules()->getModulesByMethod("restore");
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
						return [
							[
								'id' => '023f1e4a-4511-4bcd-a365-ca9ee118a5d5',
								'name' => 'Foo Backup',
								'description' => 'test data',
							]
						];
						//return array_values($this->listBackups());
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
							$items = $this->FreePBX->Filestore->listLocations('backup');
							$return = [];
							foreach ($items['locations'] as $driver => $locations ) {
								$optgroup = [
									'label' => $driver,
									'children' => []
								];
								foreach ($locations as $location) {
									$select = in_array($location['id'], $storage_ids);
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
					return [
						[
							'label' => 'test label',
							'title' => 'test title',
							'value' => 'value',
							'selected' => true,
						],
						[
							'label' => 'test label2',
							'title' => 'test title2',
							'value' => 'value2',
						],
					];
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
		return [];
	}

	/**
	 * List all Servers
	 */
	public function listServers() {

	}

	/**
	 * List all templates
	 */
	public function listTemplates() {

	}

	public function generateId(){
		return \Ramsey\Uuid\Uuid::uuid4()->toString();
	}

	/**
	 * List all backups
	 */
	public function listBackups() {
		return $this->getConfig('backupList');
	}

	public function getBackup($id){
		$data = $this->getAll($id);
		$return = array();
		foreach ($this->backupFields as $key => $value) {
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
	}
	public function deleteBackup($id){
		$this->setConfig($id,false,'backupList');
		$this->delById($id);
	}

	public function listTemplates(){
		return $this->getConfig('templateList');
	}

	public function getTemplate($id){
		$data = $this->getAll($id);
		$return = array();
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
}
