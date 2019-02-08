<?php
namespace FreePBX\modules\Backup;
use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
	public function runBackup($id,$transaction){
		$kvstoreids = $this->FreePBX->Backup->getAllids();
		$kvstoreids[] = 'noid';
		$settings = [];
		foreach ($kvstoreids as $value) {
			$settings[$value] = $this->FreePBX->Backup->getAll($value);
		}
		$this->addConfigs($settings);
	}
}