<?php
/**
 * Copyright Sangoma Technologies, Inc 2017
 */
namespace FreePBX\modules\Backup\Models;
use FreePBX\modules\Backup\Models\SplFileInfo as BackupFileSplFileInfo;
use Exception;
use FreePBX\modules\Backup\Handlers\FreePBXModule;
abstract class Restore extends ModelBase {
	protected $moduleHandler;

	/**
	 * Constructor, do not allow overridding this
	 *
	 * @param FreePBX $freepbx The FreePBX BMO Object
	 * @param array $modData The Module Data
	 * @param string $backupModVer The Backup Module's Version
	 * @param string $backupTmpDir The Backup Temporary file location
	 */
	final public function __construct($freepbx, $backupModVer, $logger, $transactionId, $modData, $backupTmpDir, $defaultFallback) {
		parent::__construct($freepbx, $backupModVer, $logger, $transactionId, $modData, $defaultFallback);
		$this->FreePBX = $freepbx;
		$this->tmpdir = $backupTmpDir;
		//Load the FreePBX Module Handler
		$this->moduleHandler = new FreePBXModule($freepbx);

		foreach($this->data['files'] as &$file) {
			$file = new BackupFileSplFileInfo(
				$this->tmpdir.'/files'.$file['pathto'].'/'.$file['filename'],
				$file['type'],
				$file['pathto'],
				$file['base']
			);
		}

		foreach($this->data['dirs'] as &$file) {
			if(!is_array($file)) {
				$file = new BackupFileSplFileInfo(
					$this->tmpdir.'/files'.$file,
					'dir',
					$file,
					''
				);
			}
		}
	}

	/**
	 * The reset method is run right before the restore method is executed
	 *
	 * You can override this and add custom code here
	 *
	 * Example. Framework skips this method by overwriting it and doing nothing
	 *
	 * @return void
	 */
	public function reset() {
		$this->moduleHandler->reset($this->data['module'], $this->data['version']);
	}

	/**
	 * The install method is run only for cdr
	 *
	 * You can override this and add custom code here
	 *
	 * @return void
	 */
	public function install($module) {
		$this->moduleHandler->install($module);
	}
}
