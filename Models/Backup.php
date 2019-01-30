<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 */
namespace FreePBX\modules\Backup\Models;

class Backup{
	private $data = [
		'dirs' => [],
		'files' => [],
		'configs' => [],
		'dependencies' => [],
		'garbage' => []
	];
	private $id = null;
	private $modified = false;

	public function __construct($freepbx){
		$this->freepbx = $freepbx;
		$this->FreePBX = $freepbx;
	}

	public function addGarbage($data){
		$this->data['garbage'][] = $data;
	}

	public function addDirs($list) {
		if (empty($list)) {
			return;
		}
		$this->modified = true;
		foreach ($list as $dir) {
			$this->data['dirs'][] = $dir;
		}
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

	public function getData(){
		return $this->data;
	}
	public function getModified(){
		return $this->modified;
	}
}
