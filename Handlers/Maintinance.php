<?php
/**
 * Copyright Sangoma Technologies, Inc 2017
 */
namespace FreePBX\modules\Backup\Handlers;

class Maintinance{
	public function __construct($freepbx = null, $backupId) {
		if ($freepbx == null) {
			throw new \Exception('Not given a FreePBX Object');
		}
		$this->dryrun = false;
		$this->FreePBX = $freepbx;
		$this->backupInfo = $this->FreePBX->Backup->getBackup($backupId);
		$this->remoteStorage = $this->FreePBX->Backup->getStorageById($backupId);
		$this->name = str_replace(' ', '_', $this->backupInfo['backup_name']);
		$this->spooldir = $this->FreePBX->Config->get("ASTSPOOLDIR");
		$this->serverName = str_replace(' ', '_',$this->FreePBX->Config->get('FREEPBX_SYSTEM_IDENT'));
		$this->localPath = sprintf('%s/backup/%s',$this->spooldir,$this->name);
		$this->remotePath =  sprintf('/%s/%s',$this->serverName,$this->name);
		$this->age = false;
		if(isset($this->backupInfo['maintage']) && $this->backupInfo['maintage'] > 1){
			$secondsperday = 86400;
			$this->age = ((int)$this->backupInfo['maintage'] * $secondsperday);
		}
	}
	public function setDryRun($mode){
		$this->dryrun = $mode;
	}
	public function processLocal(){
		$files = new \GlobIterator($this->localPath.'/*.tar.gz');
		$maintfiles = [];
		foreach ($files as $file) {
			$name = $file->getBasename();
			$date = substr($name, 7,10);
			if($file->getBasename() != 'backup-'.$date.'.tar.gz'){
				continue;
			}
			if($this->age){
				if(((int)$date + $this->age) < time()){
					if($this->dryrun){
					 dbug("UNLINK %s/%s".PHP_EOL,$file->getPath(),'backup-'.$date.'.tar.gz');
						continue;
					}
					unlink($file->getPath().'/backup-'.$date.'.tar.gz');
					continue;
				}
			}
			if($this->dryrun){
			 dbug("Adding %s/%s to maintfiles with a key of %s".PHP_EOL,$file->getPath(),'backup-'.$date.'.tar.gz',$date);
			}
			$maintfiles[$date] = $file->getPath().'/backup-'.$date.'.tar.gz';
		}
		asort($maintfiles,SORT_NUMERIC);
		if(isset($this->backupInfo['maintruns']) && $this->backupInfo['maintruns'] > 1){
			$remove = array_slice($maintfiles,$backupInfo['maintruns'],null,true);
			foreach ($remove as $key => $value) {
				if($this->dryrun){
				 dbug("UNLINK %s".PHP_EOL,$value);
					continue;
				}
				unlink($value);
			}
		}
	}
	public function processRemote(){
		$errors = [];
		foreach ($this->remoteStorage as $location) {
			$maintfiles = [];
			$location = explode('_', $location);
			try {
				$files = $this->FreePBX->Filestore->ls($location[0],$location[1],$this->remotePath);
			} catch (\Exception $e) {
				$errors[] = $e->getMessage();
				$files = [];
			}
			foreach ($files as $file) {
				if(!isset($file['path'])){
					continue;
				}
				$date = substr($file['basename'], 7,10);
				if($file['basename'] != 'backup-'.$date.'.tar.gz'){
					continue;
				}
				if($this->age){
					if(((int)$date + $this->age) < time()){
						try {
							if($this->dryrun){
							 dbug("UNLINK %s".PHP_EOL,$file['path']);
								continue;
							}
							$this->FreePBX->Filestore->delete($location[0],$location[1],$file['path']);
							$this->FreePBX->Filestore->delete($location[0],$location[1],$file['path'].'.sha256sum');
						} catch (\Exception $e) {
							$errors[] = $e->getMessage();
							continue;
						}
						continue;
					}
				}
				$maintfiles[$date] = $file['path'];
			}
			asort($maintfiles,SORT_NUMERIC);
			if(isset($this->backupInfo['maintruns']) && $this->backupInfo['maintruns'] > 1){
				$remove = array_slice($maintfiles,$this->backupInfo['maintruns'],null,true);
				foreach ($remove as $key => $value) {
					try {
						if($this->dryrun){
						 dbug("UNLINK %s".PHP_EOL,$value);
							continue;
						}
						$this->FreePBX->Filestore->delete($location[0],$location[1],$value);
						$this->FreePBX->Filestore->delete($location[0],$location[1],$value.'.sha256sum');
					} catch (\Exception $e) {
						$errors[] = $e->getMessage();
						continue;
					}
				}
			}
		}
		return empty($errors)?true:$errors;
	}
}
