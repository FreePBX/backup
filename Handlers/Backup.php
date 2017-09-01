<?php
/**
 * Copyright Sangoma Technologies, Inc 2017
 */
namespace FreePBX\modules\Backup\Handlers;

class Backup {
	private $data = []
		'dirs' => [],
		'files' => [],
		'settings' => [],
		'dependencies' => [],
	];

	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception('Not given a FreePBX Object');
		}
		$this->FreePBX = $freepbx;
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

	public function addSettings($settings){
		if (empty($settings)) {
			return;
		}
		$this->data['settings'][] = $settings;
	}

	public function getSettings(){
		return $this->data['settings'];
	}

	public function addDependency($dependency){
		$this->data['depencencies'][] = $dependency;
	}

	public function getDependencies(){
		return $this->data['dependencies'];
	}

	public function getExtraData() {
		return $this->data['extradata'];
	}
}
