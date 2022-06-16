<?php
namespace FreePBX\modules\Backup\Handlers;
use Symfony\Component\Filesystem\Filesystem;
class Storage extends CommonFile {
	private $id;
	private $remotePath;
	private $Filestore;
	/**
	 * Construct
	 *
	 * @param FreePBX $freepbx
	 * @param string $id The Backup ID
	 * @param string $file The Backup File to use
	 */
	public function __construct($freepbx, $id, $transactionId, $pid, $file) {
		parent::__construct($freepbx, $file, $transactionId, $pid);
		$this->id = $id;
		$this->backupInfo = $freepbx->Backup->getBackup($this->id);
		$this->Filestore = $this->freepbx->Filestore;
	}


	protected function translatePath($path) {
		if(preg_match("/(.*)__(.*)__(.*)/", $path, $matches) !== 1){
			return $path;
		}
		$var = $this->freepbx->Config->get($matches[2]);
		if($var === false){
			return $path;
		}
		return $matches[1].$var.$matches[3];
	}


	/**
	 * Process the Locations for the backup
	 * @param  $storages passsed from secondparty module
	 * @return void
	 */
	public function process($storages = []) {
		$storage_ids = $this->Backup->getStorageById($this->id);
		$storage_ids = count($storages)> 0?$storages:$storage_ids;
		$this->log(_("Saving to selected Filestore locations"));
		$tmpfiledelete = true;
		foreach ($storage_ids as $location) {
			if(empty(trim($location))){
				continue;
			}
			try {
				$id = explode('_', $location, 2)[1];
				$info = $this->Filestore->getItemById($id);
				if(empty($info)) {
					$this->log(_('Invalid filestore location'),'ERROR');
					continue;
				}
				$Rpath = $this->translatePath($info['path']); 
				$Rfile = basename($this->file);
				if ($this->backupInfo['backup_addbjname'] == 'yes') {
					if ($info['driver'] == 'Email') {
						$Rfile = basename($this->file);
					} else { 
						$Rfile = $this->backupInfo['backup_name'].'/'.basename($this->file);
						$this->freepbx->Filestore->makeDirectory($id, $this->backupInfo['backup_name']);
					}
				}
				if($info['driver'] == 'Local'){
					$localpath = rtrim($Rpath,'/').'/'.$Rfile;
					if($this->file == $localpath){
						$tmpfiledelete = false;
						continue;
					}
				}
				$this->Filestore->upload($id,$this->file,$Rfile);
				$this->log("\t".sprintf(_("Saving to: %s:'%s' instance ,File location: %s%s "),$info['driver'],$info['name'],$Rpath,$Rfile),'DEBUG');
			} catch (\Exception $e) {
				$err = $e->getMessage();
				$this->log($err,'ERROR');
            	$this->addError($e->getMessage());
			}
		}
		if(empty($err) && $tmpfiledelete == true){
			unlink($this->file);
		} 
		$this->log(_("Finished Saving to selected Filestore locations"));
	}

	/**
	 * Generate Hash if requested
	 *
	 * @return void
	 */
	private function generateHash() {
		return hash_file('sha256', $this->file);
	}
}
