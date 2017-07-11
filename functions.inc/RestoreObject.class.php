<?php
/**
 * Copyright Sangoma Technologies, Inc 2017
 */
namespace FreePBX\modules\Backup;

class RestoreObject {
	/* List of files added by the module to be restored. */
	private $files = array();

	private $data = array(
		'files' => array(
			array(
				'type' => 'voicemail',
				'filename' => 'msg0000.wav',
				'path' => 'default/5000/INBOX/',
			),
			array(
				'type' => 'greeting',
				'filename' => 'greet.wav',
				'path' => 'default/5000/',
			),
			array(
				'type' => 'libs',
				'filename' => 'libtaco.so',
				'path' => '/usr/lib/',
			),
		),
	);

	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception('Not given a FreePBX Object');
		}
		$this->FreePBX = $freepbx;
	}

	public function getFileData() {
		return $this->data['files'];
	}

	public function addRestoreFiles($list) {
		foreach ($list as $file) {
			$fullpath = \FreePBX\modules\Backup\BackupObject::getFilePath($file);
			if (empty($fullpath)) {
				/* We couldn't create a valid path.  Skip it. */
				// TODO Fail?  Display warning?
				continue;
			}

			$this->files[$fullpath] = $file;
		}
	}

	public function getRestoreFiles() {
		return $this->files;
	}
}
