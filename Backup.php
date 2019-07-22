<?php
namespace FreePBX\modules\Backup;
use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
	public function runBackup($id,$transaction){
		$kvstore = $this->dumpKVStore();
		$settings['kvstore'] = $kvstore;
		$this->addConfigs($settings);
	}
}
