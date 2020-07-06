<?php
namespace FreePBX\modules\Backup\Handlers;
use Symfony\Component\Filesystem\Filesystem;
use FreePBX\modules\Backup\Monolog\ConsoleOutput;
use Monolog\Formatter as Formatter;
abstract class CommonBase {
	protected $freepbx;
	protected $Backup;
	protected $logpath;
	protected $logger;
	protected $backupModVer;
	protected $pid;
	protected $fs;
	protected $tmp;
	protected $errors = [];
	protected $warnings = [];

	public function __construct($freepbx, $transactionId, $pid) {
		$this->freepbx = $freepbx;
		$this->Backup = $freepbx->Backup;
		$this->logpath = $this->freepbx->Config->get('ASTLOGDIR').'/backup-'.$transactionId.'.log';
		//Get the version of the backup module currently in use
		$this->backupModVer = (string)$this->freepbx->Modules->getInfo('backup')['backup']['version'];
		$this->transactionId = $transactionId;
		$this->fs = new Filesystem;
		$this->pid = $pid;
		//delete all OLD file which are not deleted
		$rmcommand = 'rm -rf '.sys_get_temp_dir().'/backup/*';
		shell_exec($rmcommand);
		$this->tmp = sys_get_temp_dir().'/backup/'.$this->transactionId;
	}

	protected function addError($message) {
		$this->errors[] = $message;
	}

	protected function addWarning($message) {
		$this->warnings[] = $message;
	}

	public function getErrors() {
		return $this->errors;
	}

	public function getWarnings() {
		return $this->warnings;
	}

	public function getLogger() {
		if(!$this->logger) {
			$this->setupLogger();
		}
		return $this->logger;
	}

	/**
	 * Override the logger functionality
	 *
	 * @return void
	 */
	protected function setupLogger() {
		if(!isset($this->backupInfo['backup_email']) || empty($this->backupInfo['backup_email'])){
			$this->logpath = $this->freepbx->Config->get('ASTLOGDIR').'/backup.log';
		}
		$this->logger = $this->freepbx->Logger->createLogDriver('backup', $this->logpath, \Monolog\Logger::DEBUG);
		if(php_sapi_name() == 'cli' || php_sapi_name() == 'phpdbg'){
			foreach($this->logger->getHandlers() as $handler) {
				if(is_a($handler, 'FreePBX\modules\Backup\Monolog\ConsoleOutput')) {
					//its already there
					return;
				}
			}
			$handler = new ConsoleOutput(\Monolog\Logger::DEBUG);

			$dateFormat = "Y-M-d H:i:s";
			$output = "%message%";
			$formatter = new Formatter\LineFormatter($output, $dateFormat, true);

			$handler->setFormatter($formatter);
			$this->logger->pushHandler($handler);
		}
	}

	/**
	 * Logging functionality
	 *
	 * @param string $message
	 * @param string $level
	 * @return void
	 */
	protected function log($message = '',$level = 'INFO'){
		if(!$this->logger) {
			$this->setupLogger();
		}
		$logger = $this->logger->withName($this->transactionId);
		switch ($level) {
			case 'DEBUG':
				return $logger->debug($message);
			case 'NOTICE':
				return $logger->notice($message);
			case 'WARNING':
				return $logger->warning($message);
			case 'ERROR':
				return $logger->error($message);
			case 'CRITICAL':
				return $logger->critical($message);
			case 'ALERT':
				return $logger->alert($message);
			case 'EMERGENCY':
				return $logger->emergency($message);
			case 'INFO':
			default:
				return $logger->info($message);
		}
	}
}
