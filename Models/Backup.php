<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 */
namespace FreePBX\modules\Backup\Models;

class Backup extends ModelBase {

	private $modified = false;

	final public function __construct($freepbx, $backupModVer) {
		parent::__construct($freepbx, $backupModVer);
	}

	/*
	[
		type => 'descriptor module dependent',
		filename => 'file.ext',
		pathto => '/path/to/',
		root => 'base __ASTETCDIR__ etc'.
	]
	*/
	public function addFiles($list) {
		if (empty($list)) {
			return;
		}
		$this->modified = true;
		foreach ($list as $file) {
			if (empty($file['type']) || empty($file['filename'])) {
				continue;
			}
			$this->data['files'][] = $file;
		}
	}

	public function addConfigs($settings){
		if (empty($settings)) {
			return;
		}
		$this->modified = true;
		$this->data['configs'] = $settings;
	}

	public function addDependency($dependency){
		$this->modified = true;
		$this->data['dependencies'][] = $dependency;
	}

	public function addGarbage($data){
		$this->data['garbage'][] = $data;
	}

	public function addDirectories($list) {
		if (empty($list)) {
			return;
		}
		$this->modified = true;
		foreach ($list as $dir) {
			$this->data['dirs'][] = $dir;
		}
	}

	public function getModified() {
		return $this->modified;
	}

	public function runBackup($id,$transaction) {
		throw new \Exception("Restore is not implemented");
	}
}
