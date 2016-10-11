<?php
/**
 * Copyright Sangoma Technologies, Inc 2016
 */
namespace FreePBX\modules\Backup\components;

class Communications {
	__construct($type, $config){
		$this->interface = null;
		include __DIR__.'/Communicationsmodule.class.php';
		$moduledir = new \DirectoryIterator(__DIR__);
		foreach($moduledir as $mod){
			//ignore . .. and dirs
			if($mod->isDot || $mod->isDir){
				continue;
			}
			$filename = $mod->getFilename();
			//ignore class included above
			if($filename === 'Communicationsmodule.class.php'){
				continue;
			}
			//ignore files that don't meet name format
			if(substr($filename, -9) !== 'class.php'){
				continue;
			}
			$classname = substr($filename,0,strpos($filename, '.'));
			include __DIR__.'/'.$filename;
			$interface = new $classname();
			if(!$interface->voter($protocol)){
				unset($interface);
				continue;
			}else{
				$this->interface = $interface;
				$this->interface->setConfig($config);
				break;
			}
		}
		if($this->interface === null){
			throw new \Exception("No interface found to handle your selected protocol", 1);
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
