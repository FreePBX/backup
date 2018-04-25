<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 * Handle legacy backup files
 */
namespace FreePBX\modules\Backup\Handlers;
use FreePBX\modules\Backup\Modules as Module;

class Restore{
    public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \InvalidArgumentException('Not given a BMO Object');
		}
		$this->FreePBX = $freepbx;
        $this->Backup = $freepbx->Backup;
    }
    public function process(){
        
    }
}