<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 */
namespace FreePBX\modules\Backup\Models;

class ModelBase {
	protected $FreePBX;
	protected $backupModVer;
	protected $data = [
		'dirs' => [],
		'files' => [],
		'configs' => [],
		'dependencies' => [],
		'garbage' => []
	];

	public function __construct($freepbx, $backupModVer){
		$this->FreePBX = $freepbx;
		$this->backupModVer = $backupModVer;
	}

	/**
	 * Get Directories
	 *
	 * @param array $options
	 * @return array
	 */
	public function getDirs($options = []) {
		return $this->data['dirs'];
	}

	/**
	 * Get Directories Alias
	 *
	 * @param array $options
	 * @return array
	 */
	public function getDirectories($options = []) {
		return $this->getDirs($options);
	}

	/**
	 * Get Files
	 *
	 * @param array $options
	 * @return array
	 */
	public function getFiles($options = []) {
		return $this->data['files'];
	}

	/**
	 * Get Configurations
	 *
	 * @param array $options
	 * @return array
	 */
	public function getConfigs($options = []){
		return $this->data['configs'];
	}

	/**
	 * Get Module Dependencies
	 *
	 * @param array $options
	 * @return array
	 */
	public function getDependencies($options = []){
		return $this->data['dependencies'];
	}

	/**
	 * Get Extra Data
	 *
	 * @param array $options
	 * @return array
	 */
	public function getExtraData($options = []) {
		return $this->data['extradata'];
	}

	/**
	 * Get Raw Data
	 *
	 * @return array
	 */
	public function getData(){
		return $this->data;
	}
}