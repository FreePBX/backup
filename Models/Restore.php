<?php
/**
 * Copyright Sangoma Technologies, Inc 2017
 */
namespace FreePBX\modules\Backup\Models;
use FreePBX\modules\Backup\Models\SplFileInfo;
use Exception;
class Restore extends ModelBase {
	/**
	 * Constructor, do not allow overridding this
	 *
	 * @param FreePBX $freepbx The FreePBX BMO Object
	 * @param array $modData The Module Data
	 * @param string $backupModVer The Backup Module's Version
	 * @param string $backupTmpDir The Backup Temporary file location
	 */
	final public function __construct($freepbx, $backupModVer, $modData, $backupTmpDir) {
		parent::__construct($freepbx, $backupModVer);
		$this->FreePBX = $freepbx;
		$this->tmpdir = $backupTmpDir;

		foreach($this->data as $key => $data) {
			if(!isset($modData[$key])) {
				$modData[$key] = $data;
			}
		}

		$this->data = $modData;

		foreach($this->data['files'] as &$file) {
			$file = new SplFileInfo(
				$this->tmpdir.'/files'.$file['pathto'].'/'.$file['filename'],
				$file['type'],
				$file['pathto'],
				$file['base']
			);
		}
	}
}
