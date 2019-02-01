<?php

namespace FreePBX\modules\Backup\Handlers;

abstract class CommonFile extends CommonBase {
	protected $file;
	public function __construct($freepbx, $file, $transactionId, $pid){
		parent::__construct($freepbx, $transactionId, $pid);
		$this->file = $file;
	}
}