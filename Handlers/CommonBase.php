<?php
namespace FreePBX\modules\Backup\Handlers;
use Symfony\Component\Filesystem\Filesystem;
use FreePBX\modules\Backup\Monolog\ConsoleOutput;
use Monolog\Formatter as Formatter;
#[\AllowDynamicProperties]
abstract class CommonBase {
	protected $Backup;
	protected $logpath;
	protected $logger;
	protected $backupModVer;
	protected $fs;
	protected $tmp;
	protected $errors = [];
	protected $warnings = [];

	public function __construct(protected $freepbx, $transactionId, protected $pid) {
		$this->Backup = $freepbx->Backup;
		$this->logpath = $this->freepbx->Config->get('ASTLOGDIR').'/backup-'.$transactionId.'.log';
		//Get the version of the backup module currently in use
		$this->backupModVer = (string)$this->freepbx->Modules->getInfo('backup')['backup']['version'];
		$this->transactionId = $transactionId;
		$this->fs = new Filesystem;
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
		return match ($level) {
      'DEBUG' => $logger->debug($message),
      'NOTICE' => $logger->notice($message),
      'WARNING' => $logger->warning($message),
      'ERROR' => $logger->error($message),
      'CRITICAL' => $logger->critical($message),
      'ALERT' => $logger->alert($message),
      'EMERGENCY' => $logger->emergency($message),
      default => $logger->info($message),
  };
	}
}
