<?php
/**
 * Copyright Sangoma Technologies, Inc 2016
 */
namespace FreePBX\modules\Backup\components\interfaces;

class Communications {
	public function __construct($type, $config){
		$this->interface = null;
		include __DIR__.'/Communicationsmodule.class.php';
		$moduledir = new \DirectoryIterator(dirname(__DIR__));
		foreach($moduledir as $mod){
			//ignore . ..
			if($mod->isDot()){
				continue;
			}
			if(!$mod->isDir() || $mod->getFilename() === 'interfaces'){
				continue;
			}
			$component = $mod->getFilename();
			if(file_exists($mod->getPathname().'/Communications.class.php')){
				include $mod->getPathname().'/Communications.class.php';
				$class = "\\FreePBX\modules\\Backup\\components\\$component\\Communications";
				$interface = new $class();
			}
			if(!$interface->voter($type)){
				unset($interface);
				continue;
			}else{
				$this->interface = $interface;
				$this->interface->setConfig($config);
				break;
			}
		}
		if($this->interface === null){
			throw new \Exception("No interface found to handle your selected protocol", 404);
		}
	}

	public function pull($file, $path=null){
		return $this->interface->pull($file,$path);
	}

	public function push($file, $path=null){
		return $this->interface->push($file,$path);
	}

	public function delete($file){
		return $this->interface->delete($file);
	}

	public function listFiles($path, $recursive=false){
		return $this->interface->listFiles($path,$recursive);
	}

	public function listDirectories($path, $recursive=false){
		return $this->interface->listDirectories($path,$recursive);
	}

	public function fileInfo($fullpath){
		return $this->interface->fileInfo($fullpath);
	}

	public function createDirectory($path,$recursive=false){
		return $this->interface->createDirectory($path,$recursive);
	}

	public function removeDirectory($path,$recursive=false){
		return $this->interface->removeDirectory($path,$recursive);
	}
}
