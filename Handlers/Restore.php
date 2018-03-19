<?php
/**
 * Copyright Sangoma Technologies, Inc 2017
 */
namespace FreePBX\modules\Backup\Handlers;

class Restore{
	private $data = [
		'dirs' => [],
		'files' => [],
		'configs' => [],
		'dependencies' => [],
		'garbage' => []
	];
	private $id = null;

	public function __construct($freepbx = null, $moddata) {
		if ($freepbx == null) {
			throw new \Exception('Not given a FreePBX Object');
		}
		$this->FreePBX = $freepbx;
		$this->modified = false;
		$this->data = $moddata;
		//TODO remove after new backup from bugfixed ver
		if(isset($this->data['depencencies'])){
			$this->data['dependencies'] = $this->data['depencencies'];
		}
	}


	public function setBackupId($id){
		$this->id = $id;
	}

	public function getBackupId(){
		return $this->id;
	}

	public static function getPath($file) {
		if(isset($file['root']) && !empty($file['root'])){
			return  $file['root'] . '/' . $file['path'];
		}
		if (!empty($file['path']) && !strncmp($file['path'], '/', 1)) {
			return $file['path'];
		}
		return '';
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

	public function getDirs() {
		return $this->data['dirs'];
	}

	/*
	[
		type => 'descriptor module dependent',
		filename => 'file.ext',
		path => '/path/to/',
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

	public function getFiles() {
		return $this->data['files'];
	}

	public function addConfigs($settings){
		if (empty($settings)) {
			return;
		}
		$this->modified = true;
		$this->data['configs'][] = $settings;
	}

	public function getConfigs(){
		return $this->data['configs'];
	}

	public function addDependency($dependency){
		$this->modified = true;
		$this->data['dependencies'][] = $dependency;
	}

	public function getDependencies(){
		return $this->data['dependencies'];
	}

	public function getExtraData() {
		return $this->data['extradata'];
	}
	public function getData(){
		return $this->data;
	}
	public function getModified(){
		return $this->modified;
	}
}
