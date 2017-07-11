<?php
/**
 * Copyright Sangoma Technologies, Inc 2017
 */
namespace FreePBX\modules\Backup;

class RestoreHandler {
	/* List of files added by the module to be restored. */
	private $files = array();

	public function __construct($freepbx = null, $data = null) {
		if ($freepbx == null) {
			throw new \Exception('Not given a FreePBX Object');
		}
		$this->FreePBX = $freepbx;
		$this->data = $data;
	}

	/* Called by a module to get the list of files that have been backed up. */
	public function getBackupFiles() {
		return $this->data['files'];
	}

	public function addFiles($list) {
		foreach ($list as $file) {
			$this->files[] = $file;
		}
	}

	/* Called by the Backup class to get the list of files to move into place. */
	public function getFiles() {
		return $this->files;
	}
}
