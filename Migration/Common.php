<?php
namespace FreePBX\modules\Backup\Migration;
class Common {
	public function __construct($freepbx){
		$this->freepbx = $freepbx;
		$this->Database = $freepbx->Database;
		$this->Backup = $freepbx->Backup;
	}
}