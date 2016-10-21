<?php
/**
 * Copyright Sangoma Technologies, Inc 2016
 */
namespace FreePBX\modules\Backup\components\Ftp;
require __DIR__ . '/../../vendor/autoload.php';
use Touki\FTP\FTP;
use Touki\FTP\FTPWrapper;
use Touki\FTP\Connection\Connection;
use Touki\FTP\Connection\SSLConnection;
use Touki\FTP\PermissionsFactory;
use Touki\FTP\FilesystemFactory;
use Touki\FTP\WindowsFilesystemFactory;
use Touki\FTP\DownloaderVoter;
use Touki\FTP\UploaderVoter;
use Touki\FTP\CreatorVoter;
use Touki\FTP\DeleterVoter;
use Touki\FTP\Manager\FTPFilesystemManager;
use Touki\FTP\Model\File;
use Touki\FTP\Model\Directory;
use Touki\FTP\Exception\DirectoryException;
use Touki\FTP\Exception\ConnectionEstablishedException;
use Touki\FTP\Exception\InvalidArgumentException;

class Communications implements \FreePBX\modules\Backup\components\interfaces\Communicationsmodule {
	public function voter($protocol){
		switch ($protocol) {
			case 'ftp':
			case 'sftp':
				$this->type = $protocol;
				return true;
			break;
			default:
				return false;
			break;
		}
	}
	public function setConfig($config=array()){
		//Default Configs.
		$configopts = array(
			'fstype' => 'auto',
			'host' => '',
			'user' => 'anonymous',
			'password' => '',
			'port' => '21',
			'transfer' => 'passive'
		);
		foreach ($configopts as $key => $value) {
			isset($config[$key])?$config[$key]:$value;
		}
		switch ($this->type) {
			case 'sftp':
				$connection = new SSLConnection($config['host'], $config['user'], $config['password'], $config['port'], 90, ($config['transfer'] === 'passive'));
			break;
			default:
				$connection = new Connection($config['host'], $config['user'], $config['password'], $config['port'], 90, ($config['transfer'] === 'passive'));
			break;
		}

		try{
			$connection->open();
		}catch (ConnectionEstablishedException $e){
			throw new Exception("Connection Error: " . $e->getMessage(), 1);
		}
		$this->wrapper = new FTPWrapper($connection);
		$permFactory = new PermissionsFactory;
		switch ($fstype) {
			case 'unix':
				$fsFactory = new FilesystemFactory($permFactory);
			break;
			case 'windows':
				$fsFactory = new WindowsFilesystemFactory;
			break;
			case 'auto':
			default:
				$ftptype = $this->wrapper->systype();
				if(strtolower($ftptype) == "unix"){
					$fsFactory = new FilesystemFactory($permFactory);
				}else{
					$fsFactory = new WindowsFilesystemFactory;
				}
			break;
		}

		$this->manager = new FTPFilesystemManager($this->wrapper, $fsFactory);
		$this->dlVoter = new DownloaderVoter;
		$this->dlVoter->addDefaultFTPDownloaders($this->wrapper);
		$this->ulVoter = new UploaderVoter;
		$this->ulVoter->addDefaultFTPUploaders($this->wrapper);
		$this->crVoter = new CreatorVoter;
		$this->crVoter->addDefaultFTPCreators($this->wrapper, $this->manager);
		$this->deVoter = new DeleterVoter;
		$this->deVoter->addDefaultFTPDeleters($this->wrapper, $this->manager);
		$this->ftp =  new FTP($this->manager, $this->dlVoter, $this->ulVoter, $this->crVoter, $this->deVoter);
		if(!$this->ftp){
			throw new \Exception("Error creating the FTP object", 1);
		}
	}

	public function pull($file, $path){
		$pp = pathinfo($file);
		$f = $this->ftp->findFileByName($pp['basename']);
		if($f === null){
			return false;
		}
		$options = array(
    			FTP::NON_BLOCKING  => false,     // Whether to deal with a callback while downloading
    			FTP::TRANSFER_MODE => FTP_BINARY // Transfer Mode
				);
		return $this->ftp->download($path, $f, $options);
	}

	public function push($path, $file){
		$pp = pathinfo($path);
		$pwd = $this->wrapper->pwd();
		$this->wrapper->chdir($pp['dirname']);
		$remote = new File($pp['basename']);
		try {
			$ret = $this->ftp->upload($remote,$file);
		} catch (InvalidArgumentException $e) {
			throw new \Exception("Unable to upload file ".$e->getMessage(), 1);
		}
		$this->wrapper->chdir($pwd);
		return $ret;
	}

	public function delete($file){
		$pp = pathinfo($file);
		$f = $this->ftp->findFileByName($pp['basename']);
		if(!$f){
			return array('status'=> false, 'message' => _("Couldn't find file"));
		}
		try {
			$this->ftp->delete($f);
		} catch (DirectoryException $e) {
			throw new Exception("Couldn't delete file ".$e->getMessage(), 1);
		}
		return array('status'=> trhe, 'message' => '');
	}

	public function listFiles($path, $recursive=false){
		$ftplist = $this->ftp->findFilesystems(new Directory($path));
		$files = array();
		$dirs = array();
		foreach($ftplist as $ftpitem){
			$files[$ftpitem->getRealpath()] = $ftpitem->getRealpath();
			if((is_object($ftpitem) && @get_class($ftpitem) === 'Touki\FTP\Model\Directory') && $recursive === true){
				$arr = $this->listFiles($ftpitem->getRealpath(),true);
				$files = array_merge($files,$arr);
				unset($arr);
			}else{
					$files[$ftpitem->getRealpath()] = $ftpitem->getRealpath();
			}
		}
		return $files;
	}

	public function listDirectories($path, $recursive=false){
		$ftplist = $this->ftp->findFilesystems(new Directory($path));
		$files = array();
		$dirs = array();
		foreach($ftplist as $ftpitem){
			if(is_object($ftpitem) && @get_class($ftpitem) === 'Touki\FTP\Model\Directory'){
				continue;
			}
			$files[$ftpitem->getRealpath()] = $ftpitem->getRealpath();
			if((is_object($ftpitem) && @get_class($ftpitem) === 'Touki\FTP\Model\Directory') && $recursive === true){
				$arr = $this->listDirectories($ftpitem->getRealpath(),true);
				$files = array_merge($files,$arr);
				unset($arr);
			}
		}
		return $files;
	}

	public function fileInfo($fullpath){
		$pp = pathinfo($fullpath);
		$f = $this->ftp->findFileByName($pp['basename'], new Directory($pp['dirname']));
		if($f){
			$ret = array('size' => $f->getSize(), 'lastmodified' => $f->getMtime() ,'owner' => $f->getOwner(), 'group' => $f->getGroup());
		}else{
			$ret = false;
		}
		return $ret;
	}

	public function createDirectory($path,$recursive=false){
		array(
			FTP::RECURSIVE => $recursive
		);
		$this->ftp->create(new Directory($path, $options));
	}

	public function removeDirectory($path,$recursive=false){
		$options = array(
			FTP::RECURSIVE => $recursive
		);
		$this->ftp->delete(new Directory($path), $options);
	}
}
