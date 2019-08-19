<?php
namespace FreePBX\modules\Backup;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
	public function runRestore(){
		$settings = $this->getConfigs();
		$this->importKVStore($settings['kvstore']);
	}
	public function processLegacy($pdo, $data, $tables, $unknownTables){
		$this->log('Skipping Legacy Backup module ');
	}
}
