<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 */
namespace FreePBX\modules\Backup\Handlers\Restore;
use function FreePBX\modules\Backup\Json\json_decode;
use function FreePBX\modules\Backup\Json\json_encode;
use splitbrain\PHPArchive\Tar;
use modgettext;
abstract class Common extends \FreePBX\modules\Backup\Handlers\CommonFile {
	protected $webroot;
	protected $specificRestores;
	protected $defaultFallback = false;
	protected $existingports = [];
	protected $changedports = [];
	protected $restorepid = '/var/run/asterisk/restore_running.lock';
	protected $legacycdrrestore = 0;

	public function __construct($freepbx, $file, $transactionId, $pid,$cdrlegacyrestore = 0) {
		parent::__construct($freepbx, $file, $transactionId, $pid);

		$this->webroot = $this->freepbx->Config->get('AMPWEBROOT');
		if ($this->freepbx->Modules->checkStatus("sysadmin")) {
			$this->existingports = $this->freepbx->Sysadmin->getPorts();
		}
		//acquire the restore lock
		$this->setRestoreStart();
		$this->legacycdrrestore = $cdrlegacyrestore;
	}

	/**
	 * Set default Fallback flag
	 *
	 * @param boolean $value
	 * @return void
	 */
	public function setDefaultFallback($value) {
		$this->defaultFallback = !empty($value);
	}

	/**
	 * Set this to a module to restore only that module from the backup
	 *
	 * @param string $module
	 * @return void
	 */
	public function setSpecificRestore($modules) {
		$this->specificRestores = $modules;
	}

	/**
	 * Process the restore method
	 *
	 * This should be declared outside the scope of this
	 *
	 * @return void
	 */
	public function process() {
		throw new \Exception("Nothing to process!");
	}

	/**
	 * Process Individual Module Manifest from the backup
	 *
	 * @param string $module The module rawname
	 * @return array The module's manifest
	 */
	protected function getModuleManifest($module) {
		//Get module specific manifest
		$modJsonPath = $this->tmp . '/modulejson/' . ucfirst($module) . '.json';
		if(!file_exists($modJsonPath)){
			return [];
		}
		return json_decode(file_get_contents($modJsonPath), true);
	}

	/**
	 * Process Individual Module from the backup
	 *
	 * @param string $module The module's rawname
	 * @param string $version The module version the backup references
	 * @return void
	 */
	protected function processModule($module, $version) {
		$modData = $this->getModuleManifest($module);
		if(empty($modData)) {
			$msg = sprintf(_("Can't find the module data for %s"),$module);
			$this->log($msg,'WARNING');
			$this->addWarning($msg);
			return;
		}
		$modData['module'] = $module;
		$modData['version'] = $version;
		$modData['pbx_version'] = null;
		if(strtolower($module) === 'framework') {
			$className = 'FreePBX\Builtin\Restore';
		} else {
			$className = sprintf('\\FreePBX\\modules\\%s\\Restore', ucfirst($module));
		}

		if(!class_exists($className)) {
			$this->log(sprintf(_("The module %s does not seem to support restores."), $module));
			if($module === 'framework' || !$this->defaultFallback) {
				return;
			}
			$this->log(_("Using fallback restore strategy"),'WARNING');
			$className = 'FreePBX\modules\Backup\RestoreBase';
		}
		$class = new $className($this->freepbx, $this->backupModVer, $this->getLogger(), $this->transactionId, $modData, $this->tmp, $this->defaultFallback);
		//Change the Text Domain
		$this->log(sprintf(_('Resetting %s module data'),$module));
		modgettext::push_textdomain($module);
		$class->reset();
		$this->log(sprintf(_("Restoring from %s [%s]"), $module, get_class($class)));
		$this->runRestore($class);
		$this->log(modgettext::_('Done','backup'));
		modgettext::pop_textdomain();
	}

	/**
	 * Allows one to override the called module restore function
	 *
	 * @param object $class
	 * @return void
	 */
	protected function runRestore($class) {
		$class->runRestore();
	}

	/**
	 * Extract the backup file to the tmp location
	 *
	 * @return void
	 */
	protected function extractFile() {
		$this->log(_("Extracting backup..."));
		//remove backup tmp dir for extraction
		$this->fs->remove($this->tmp);
		//add tmp dir back
		$this->fs->mkdir($this->tmp);

		//prepare to extract file
		$tar = new Tar();
		$tar->open($this->file);
		$tar->extract($this->tmp);
		$tar->close();
		$this->log(sprintf(_("Backup extracted to %s. These files will remain until a new restore is run or until cleaned manually."),$this->tmp));
	}

	/**
	 * Process the Master Manifest
	 *
	 * @return array
	 */
	protected function getMasterManifest() {
		//attempt to read manifest from tar
		$metapath = $this->tmp . '/metadata.json';
		if(!file_exists($metapath)){
			throw new \Exception(_("Could not locate the manifest for this file"));
		}
		$restoreData = json_decode(file_get_contents($metapath), true);
		return $restoreData;
	}

	/**  retrun the ports which were in use before restore**/
	protected function displayportschanges() {
		if (!$this->freepbx->Modules->checkStatus("sysadmin")) {
			return;
		}
		$this->getportschanges();
		if(is_array($this->changedports) && count($this->changedports)> 0){
			$this->log(_("Apache will Restat now... And your GUI may die if the ports are changed !!!!"));
			foreach($this->changedports as $key => $port){
				if($key == 'acp' || $key == 'sslacp') {
					if(!strpos($port, 'available')){
						$this->log("New port for accessing $key = $port ");
						$this->log($_SERVER['SERVER_ADDR'].":$port/admin/config.php?display=backup ");
					}
				}
			}
		}
	}

	/** Get new ports after restoring */
	private function getportschanges() {
		$sysvals = $this->freepbx->Database->query("SELECT * FROM sysadmin_options")->fetchAll();
		foreach($sysvals as $keyvalue) {
			$ports[$keyvalue['key']] = $keyvalue['value'];
		}
		foreach($this->existingports as $key => $value) {
			if($ports[$key] != $value) { // port has changed
				$this->changedports[$key] = $ports[$key];
			}
		}
	}

	/**
	* run the sysadmin hook post restore 
	*/
	protected function postRestoreHooks(){
		// Trigger sysadmin to reload/regen any settings if available
		if (is_dir("/var/spool/asterisk/incron")) {
			$triggers = array('update-dns', 'config-postfix', 'update-ftp', 'fail2ban-generate', 'update-mdadm', 'update-ports', 'update-ups');
			foreach ($triggers as $f) {
				 $filename = "/var/spool/asterisk/incron/sysadmin.$f";
				 if (file_exists($filename)) {
					 @unlink($filename);
				 }
				 @fclose(@fopen($filename, "w"));
			}
		} else {
			$this->log('post Restore hooks failed !!!!!');
		}
	}


	/**
	 * Create Restore process id
	 */
	public function setRestoreStart() {
		$fh = fopen($this->restorepid, "w+");
		if ($fh === false) {
			throw new Exception("Failed to create restore process id file $$this->restorepid");
		}
		fclose($fh);
	}

	/**
	 * Destroy Restore process id
	 */
	public function setRestoreEnd() {
		if (file_exists($this->restorepid)) {
			unlink($this->restorepid);
		}
	}


}
