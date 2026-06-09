<?php
namespace FreePBX\modules\Backup;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
	/**
	 * Never uninstall/reinstall backup during a restore — this job runs inside backup.
	 */
	public function reset() {
	}

	public function runRestore(){
		$settings = $this->getConfigs();
		$this->importKVStore($settings['kvstore']);
	}
	public function processLegacy($pdo, $data, $tables, $unknownTables){
		$this->log('Skipping Legacy Backup module ');
	}
}
