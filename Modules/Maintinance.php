<?php
/**
 * Copyright Sangoma Technologies, Inc 2017
 */
namespace FreePBX\modules\Backup\Modules;

use Carbon\Carbon;
use FreePBX\modules\Backup\Handlers as Handler;

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
	}
	public function setDryRun($mode){
		$this->dryrun = $mode;
	}
	public function processLocal(){
		$files = new \GlobIterator($this->localPath.'/*.tar.gz*');
		$maintfiles = [];
		foreach ($files as $file) {
			$parsed = Handler\Backup::parseFile($file->getBasename());
			if($parsed === false){
				continue;
			}
			$backupDate = Carbon::createFromTimestamp($parsed['timestamp'], 'UTC');
			if(isset($this->backupInfo['maintage']) && $this->backupInfo['maintage'] > 1){
				if($backupDate->diffInDays() > $backupInfo['maintage']){
					if($this->dryrun){
					 dbug(sprintf("UNLINK %s/%s".PHP_EOL,$file->getPath(),$file->getBasename().'.tar.gz'));
						continue;
					}
					unlink($file->getPath().'/'.$file->getBasename());
					continue;
				}
			}
			if($this->dryrun){
			 dbug(sprintf("Adding %s/%s to maintfiles with a key of %s".PHP_EOL,$file->getPath(),$file->getBasename(),$parsed['timestamp']));
			}
			if(!$parsed['isCheckSum']){
				$maintfiles[$parsed['timestamp']] = $file->getPath().'/'.$file->getBasename();
			}
		}
		asort($maintfiles,SORT_NUMERIC);
		if(isset($this->backupInfo['maintruns']) && $this->backupInfo['maintruns'] > 1){
			$remove = array_slice($maintfiles,$this->backupInfo['maintruns'],null,true);
			foreach ($remove as $key => $value) {
				if($this->dryrun){
				 dbug(sprintf("UNLINK %s".PHP_EOL,$value));
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
				$parsed = Handler\Backup::parseFile($file['basename']);
				if($parsed === false){
					continue;
				}
				$backupDate = Carbon::createFromTimestamp($parsed['timestamp'], 'UTC');
				if(isset($this->backupInfo['maintage']) && $this->backupInfo['maintage'] > 1){
					if($backupDate->diffInDays() > $backupInfo['maintage']){
						try {
							if($this->dryrun){
							 dbug(sprintf("UNLINK %s".PHP_EOL,$file['path']));
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
				if(!$parsed['isCheckSum']){
					$maintfiles[$parsed['timestamp']] = $file['path'];
				}
			}
			asort($maintfiles,SORT_NUMERIC);
			if(isset($this->backupInfo['maintruns']) && $this->backupInfo['maintruns'] > 1){
				$remove = array_slice($maintfiles,$this->backupInfo['maintruns'],null,true);
				foreach ($remove as $key => $value) {
					try {
						if($this->dryrun){
						 dbug(sprintf("UNLINK %s".PHP_EOL,$value));
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
