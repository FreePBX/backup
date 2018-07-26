<?php
namespace FreePBX\modules\Backup\Modules;
class Migration{
	const DEBUG = true;
	public function __construct($freepbx = ''){
		$this->FreePBX = $freepbx;
		$this->Database = $freepbx->Database;
		$this->Backup = $freepbx->Backup;
	}
}