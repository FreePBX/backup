<?php
namespace FreePBX\modules\Backup\Migration;
class Common {
	protected $freepbx;
	protected $Database;
	protected $Backup;

	public function __construct($freepbx){
		$this->freepbx = $freepbx;
		$this->Database = $freepbx->Database;
		$this->Backup = $freepbx->Backup;
	}
}