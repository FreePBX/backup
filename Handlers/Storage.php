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

	/**
	 * Process the Locations for the backup
	 *
	 * @return void
	 */
	public function process() {
		$storage_ids = $this->Backup->getStorageById($this->id);

		$this->log(_("Saving to selected Filestore locations"));
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
				$path = trim($info['path'],"'");
				if (substr($path,0,2) == '__') {
					$path = ltrim($path,'__');
					$position = strpos($path, '__');
					if($position > 0){
						$basepath = substr($path,0,$position);
						$additionalpath = substr($path,$position+2);
					}
					$Rpath = $this->freepbx->config->get($basepath);
					$Rpath .= $additionalpath;
				} else {
					$Rpath = $path;
				}
				$Rpath = rtrim($Rpath,'/');
				$this->Filestore->upload($id,$this->file,basename($this->file));
				$this->log("\t".sprintf(_("Saving to: %s:'%s' instance ,File location: %s/%s "),$info['driver'],$info['name'],$Rpath,basename($this->file)),'DEBUG');
			} catch (\Exception $e) {
				$err = $e->getMessage();
				$this->log($err,'ERROR');
            	$this->addError($e->getMessage());
			}
		}
  		if(empty($err)){
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
