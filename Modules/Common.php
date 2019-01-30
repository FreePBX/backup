<?php
namespace FreePBX\modules\Backup\Modules;
class Common {
	public function __construct($freepbx = ''){
		$this->freepbx = $freepbx;
		$this->Database = $freepbx->Database;
		$this->Backup = $freepbx->Backup;
	}
}