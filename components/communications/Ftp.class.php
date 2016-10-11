<?php
/**
 * Copyright Sangoma Technologies, Inc 2016
 */
namespace FreePBX\modules\Backup\components\Communications;

class Ftp implements Communicationsmodule {
	public function voter($protocol){
		switch ($protocol) {
			case 'ftp':
			case 'sftp':
				return true;
			break;
			default:
				return false;
			break;
		}
	}
	public function setConfig($config){

	}
	public function pull($file, $path=null){}

	public function push($file, $path=null){}

	public function delete($file){}

	public function listFiles($path, $recursive=false){}

	public function listDirectories($path, $recursive=false){}

	public function fileInfo($fullpath){}

	public function createDirectory($path,$recursive=false){}

	public function removeDirectory($path,$recursive=false){}
}
