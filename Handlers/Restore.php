<?php
/**
 * Copyright Sangoma Technologies, Inc 2017
 */
namespace FreePBX\modules\Backup\Handlers;

class Restore {
	/* List of dirs added by the module to be restored. */
	private $dirs = array();
	/* List of files added by the module to be restored. */
	private $files = array();

	public function __construct($freepbx = null, $data = null) {
		if ($freepbx == null) {
			throw new \Exception('Not given a FreePBX Object');
		}
		$this->FreePBX = $freepbx;
		$this->data = $data;
	}

	public function getBackupDirs() {
		return $this->data['dirs'];
	}

	public function getBackupFiles() {
		return $this->data['files'];
	}

	public function addDirs($list) {
		foreach ($list as $dir) {
			$this->dirs[] = $dir;
		}
	}

	/* Called by the Backup class to get the list of dirs to create. */
	public function getDirs() {
		return $this->dirs;
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
