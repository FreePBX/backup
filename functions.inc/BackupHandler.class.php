<?php
/**
 * Copyright Sangoma Technologies, Inc 2017
 */
namespace FreePBX\modules\Backup;

class BackupHandler {
	private $data = array(
		'dirs' => array(),
		'files' => array(),
	);

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

	/**
	array(
		'type' => '',		// The type of the file.  Something that the module can understand as something useful and do something with.
					// 'voicemail', 'greeting', 'libs'

		'filename' => '',	// It's a filename.  You know what a filename is.
					// 'data.dat', 'msg0001.wav', 'libtaco.so'

		'path' => '',		// Unless a full path is given, this is a relative path. It is left to the module to figure out where the file should go.
					// '', 'default/5000/INBOX', '/usr/lib/'

		'root' => '',		//
					// '', '__ASTETCDIR__', '__ASTSPOOLDIR__/voicemail/'
	);
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

	public function getExtraData() {
		return $this->data['extradata'];
	}
}
