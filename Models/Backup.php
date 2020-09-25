<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 */
namespace FreePBX\modules\Backup\Models;

class Backup extends ModelBase {

	private $modified = false;

	final public function __construct($freepbx, $backupModVer, $logger, $transactionId, $modData, $defaultFallback) {
		parent::__construct($freepbx, $backupModVer, $logger, $transactionId, $modData, $defaultFallback);
	}

	/* set moddata 
	 * this method to set skip_rest to true 
	 * to avoid resetting the mdoule while restore
	 */
	public function setskipreset(){
		foreach($this->data as $key => $data) {
		if($key == 'skip_reset'){
				$modData[$key] = true;
			}else{
				$modData[$key] = $data;
			}
		}
		$this->data = $modData;
	}

	/**
	 * Add Multiple files as an array
	 *
	 * [
	 * 	type => 'descriptor module dependent',
	 * 	filename => 'file.ext',
	 * 	pathto => '/path/to/',
	 * 	root => 'base __ASTETCDIR__ etc'.
	 * ]
	 *
	 * @param array $list The files to add
	 * @return void
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

	/**
	 * Add Configuration item this should be an array. The contents will depend on your module.
	 *
	 * @param array $settings Multidimensional Array of settings to add
	 * @return void
	 */
	public function addConfigs($settings){
		if (empty($settings)) {
			return;
		}
		$this->modified = true;
		$this->data['configs'] = $settings;
	}

	/**
	 * Add single config by key to the configs array
	 *
	 * @param string $key The key in the array to add the data
	 * @param mixed $settings Data to insert
	 * @return void
	 */
	public function addConfig($key, $settings) {
		if (empty($settings)) {
			return;
		}
		$this->modified = true;
		$this->data['configs'][$key] = $settings;
	}

	/**
	 * Add a single dependency for this module
	 *
	 * These are dependencies for restore. During the module reset dependencies should be resolved. This can be called multiple times.
	 *
	 * @param string $dependency
	 * @return void
	 */
	public function addDependency($dependency){
		$this->modified = true;
		$this->data['dependencies'][] = $dependency;
	}

	/**
	 * Add Garbage that is cleaned up last
	 *
	 * deletes files, directories and symlinks
	 *
	 * @param string $file deletes files, directories and symlinks
	 * @return void
	 */
	public function addGarbage($file){
		$this->data['garbage'][] = $file;
		$this->data['garbage'] = array_unique($this->data['garbage']);
	}

	/**
	 * Add multiple files to the garabage list
	 *
	 * deletes files, directories and symlinks
	 *
	 * @param array $list deletes files, directories and symlinks
	 * @return void
	 */
	public function addGarbages($list) {
		if (empty($list)) {
			return;
		}
		$this->modified = true;
		foreach ($list as $file) {
			$this->data['garbage'][] = $file;
		}
		$this->data['garbage'] = array_unique($this->data['garbage']);
	}

	/**
	 * Add multiple Directories
	 *
	 * Add Directories you use IF you are backing up files item this should be an array. The contents will depend on your module.
	 *
	 * @param array $list
	 * @return void
	 */
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

	/**
	 * Add Single file to Files List
	 *
	 * @param string $filename The file name: File.ext
	 * @param string $path /path/to/file (no trailing slash)
	 * @param string $base If you are using a path variable such as VARLIBDIR
	 * @param string $type A file type identifier. This is module dependent and can be any string.
	 * @return void
	 */
	public function addFile($filename,$path,$base,$type = "file"){
		$this->addFiles([['type' => $type, 'filename' => $filename, 'pathto' => $path,'base' => $base]]);
	}

	/**
	 * Utilizes SplFileInfo to add a file
	 *
	 * @param \SplFileInfo $file
	 * @return void
	 */
	public function addSplFile(\SplFileInfo $file){
		$this->addFiles([['type' => $file->getExtension(), 'filename' => $file->getBasename(), 'pathto' => $file->getPath(),'base' => '']]);
	}
}
