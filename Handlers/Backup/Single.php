<?php
/**
 * Copyright Sangoma Technologies 2018
 */
namespace FreePBX\modules\Backup\Handlers\Backup;
use Symfony\Component\Console\Output\ConsoleOutput;
use function FreePBX\modules\Backup\Json\json_decode;
use function FreePBX\modules\Backup\Json\json_encode;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter;
/**
 * Class used for Single Module Backup
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

	public function setModule($module) {
		$this->module = $module;
	}

	public function process(){
		if(empty($this->module)) {
			throw new \Exception("Unknown Module!");
		}
		$moduleInfo = $this->freepbx->Modules->getInfo($this->module)[$this->module];
		if(empty($moduleInfo)) {
			throw new \Exception(sprintf(_('Unknown module %s'),$this->module));
		}

		//setup tarball format
		$tarfilename = sprintf('%s-%s%s-%s-%s', $this->module, date("Ymd-His-"), time(), $moduleInfo['version'], rand());
		$targzname = sprintf('%s.tar.gz', $tarfilename);

		$this->setFilename($targzname);

		$tar = $this->openFile();

		$this->log(sprintf(_("Processing %s"),$this->module));
		$moddata = $this->processModule($this->module);

		if(!empty($moddata['dependencies'])) {
			$this->log("***"._("In single restores mode dependencies are NOT processed")."***",'WARNING');
		}

		$manifest = array(
			'moddata' => $moddata,
			'module' => $moduleInfo
		);

		$tar->addData('metadata.json', json_encode($manifest, JSON_PRETTY_PRINT));

		$this->closeFile();


		exit(sprintf("Your backup can be found at: %s".PHP_EOL, $this->getFile()));

		die();













	}

}
