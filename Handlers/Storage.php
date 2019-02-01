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
		$underscoreName = str_replace(' ', '_', $backupInfo['backup_name']);
		$this->remotePath =  sprintf('/%s/%s',$serverName,$underscoreName);
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
				$location = explode('_', $location, 2);
				$this->Filestore->put($location[0],$location[1],file_get_contents($this->file),$this->remotePath.'/'.basename($this->file));
				$this->log("\t".sprintf(_("Saving to: %s instance"),$location[0]),'DEBUG');
			} catch (\Exception $e) {
				$err = $e->getMessage();
				$this->log($err,'ERROR');
			}
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