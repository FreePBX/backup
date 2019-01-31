<?php
/**
 * Copyright Sangoma Technologies Inc 2018
 */
namespace FreePBX\modules\Backup\Handlers\Restore;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use function FreePBX\modules\Backup\Json\json_decode;
use function FreePBX\modules\Backup\Json\json_encode;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter;
/**
 * Class used for a single module restore
 */
class Single extends Common {

	/**
	 * Override the common logger removing the transaction id
	 * since we are in single mode theres no need to see that
	 *
	 * @return void
	 */
	protected function setupLogger() {
		parent::setupLogger();
		$handler = new StreamHandler("php://stdout",\Monolog\Logger::DEBUG);
		$output = "%message%\n";
		$formatter = new Formatter\LineFormatter($output);
		$handler->setFormatter($formatter);
		$this->logger->pushHandler($handler);
	}
	/**
	 * Process the single restore
	 *
	 * @return void
	 */
	public function process() {
		$this->extractFile();
		$restoreData = $this->getMasterManifest();

		$this->log("***"._("In single restores mode dependencies are NOT processed")."***",'WARNING');
		$this->processModule($restoreData['module']['rawname'],$restoreData['module']['version']);

		$this->log(_('Finished'));
		needreload();
	}
}
