<?php

namespace FreePBX\modules\Backup\Handlers;

abstract class CommonFile extends CommonBase {
	public function __construct($freepbx, protected $file, $transactionId, $pid){
		parent::__construct($freepbx, $transactionId, $pid);
	}
}