<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 */
namespace FreePBX\modules\Backup\Handlers\Restore;
use function FreePBX\modules\Backup\Json\json_decode;
use function FreePBX\modules\Backup\Json\json_encode;
use splitbrain\PHPArchive\Tar;
use FreePBX\modules\Backup\Handlers\FreePBXModule;
use modgettext;
abstract class Common extends \FreePBX\modules\Backup\Handlers\CommonBase {
	protected $webroot;
	protected $modulehandler;

	public function __construct($freepbx, $file, $transactionId, $pid) {
		parent::__construct($freepbx, $file, $transactionId, $pid);

		$this->webroot = $this->freepbx->Config->get('AMPWEBROOT');

		//Load the FreePBX Module Handler
		$this->modulehandler = new FreePBXModule($this->freepbx);
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
			throw new \Exception(sprintf(_("Can't find the module data for %s"),$module));
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
		$class = sprintf('\\FreePBX\\modules\\%s\\Restore', ucfirst($module));
		$class = new $class($this->freepbx, $this->backupModVer, $modData, $this->tmp);
		if(!method_exists($class, 'runRestore')) {
			$this->log('runRestore method does not exist in '.$class);
			return;
		}
		//Change the Text Domain
		$this->log(sprintf(_('Resetting %s module data'),$module));
		modgettext::push_textdomain($module);
		$this->modulehandler->reset($module, $version);
		modgettext::pop_textdomain();
		$this->log(_('Restoring Data...'));
		modgettext::push_textdomain($module);
		$this->log(_('Done'));
		$class->runRestore($this->transactionId);
		modgettext::pop_textdomain();
	}

	/**
	 * Extract the backup file to the tmp location
	 *
	 * @return void
	 */
	protected function extractFile() {
		//remove backup tmp dir for extraction
		$this->fs->remove($this->tmp);

		//add tmp dir back
		$this->fs->mkdir($this->tmp);

		//prepare to extract file
		$tar = new Tar();
		$tar->open($this->file);
		$tar->extract($this->tmp);
		$tar->close();
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
}