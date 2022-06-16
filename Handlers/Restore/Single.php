<?php
/**
 * Copyright Sangoma Technologies Inc 2018
 */
namespace FreePBX\modules\Backup\Handlers\Restore;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use function FreePBX\modules\Backup\Json\json_decode;
use function FreePBX\modules\Backup\Json\json_encode;
use Monolog\Formatter;
/**
 * Class used for a single module restore
 */
class Single extends Common {

	/**
	 * Process the single restore
	 *
	 * @return void
	 */
	public function process($phpcompatible_error='') {
		$this->extractFile();
		$restoreData = $this->getMasterManifest();

		$this->log("***"._("In single restores mode dependencies are NOT processed")."***",'WARNING');
		try {
			$this->processModule($restoreData['module']['rawname'],$restoreData['module']['version']);
		} catch(\Exception $e) {
			$this->log($e->getMessage(),'ERROR');
		}


		$this->log(_('Finished'));
		needreload();
	}
}
