<?php
/**
 * Copyright Sangoma Technologies, Inc 2017
 */
namespace FreePBX\modules\Backup\Models;
use Exception;
class Restore{
	private $data = [
		'dirs' => [],
		'files' => [],
		'configs' => [],
		'dependencies' => [],
		'garbage' => []
	];

	final public function __construct($freepbx, $modData, $backupModVer, $backuptmpdir) {
		$this->modified = false;
		$this->data = $moddata;
		//TODO remove after new backup from bugfixed ver
		if(isset($this->data['depencencies'])){
			$this->data['dependencies'] = $this->data['depencencies'];
		}
		$this->FreePBX = $freepbx;
		$this->tmpdir = $tmpdir;
		foreach($this->data['files'] as &$file) {
		  $file = new SplFileInfo(
			$this->tmpdir.'/files'.$file['pathto'].'/'.$file['filename'],
			$file['type'],
			$file['pathto'],
			$file['base']
		  );
		}
	}

	public function getDirs() {
		return $this->data['dirs'];
	}

	public function getFiles() {
		return $this->data['files'];
	}

	public function getConfigs($options = []){
		//old formatting
		if(is_array($this->data['configs']) && count($this->data['configs']) === 1 && isset($this->data['configs'][0])) {
			return $this->data['configs'][0];
		}
		return $this->data['configs'];
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
