<?php
/**
 * Copyright Sangoma Technologies, Inc 2017
 */
namespace FreePBX\modules\Backup\Handlers;

class Backup{
	private $data = [
		'dirs' => [],
		'files' => [],
		'configs' => [],
		'dependencies' => [],
	];

	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception('Not given a FreePBX Object');
		}
		$this->FreePBX = $freepbx;
		$this->modified = false;
	}

	public static function getPath($file) {
		$fullpath = '';
		if (empty($file['root'])) {
			if (!empty($file['path']) && !strncmp($file['path'], '/', 1)) {
				/* We have a full path, rather than a relative path. */
				$fullpath = $file['path'];
			}
		} else {
			$fullpath = $file['root'] . '/' . $file['path'];
		}

		return $fullpath;
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
		$this->data['depencencies'][] = $dependency;
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
}
