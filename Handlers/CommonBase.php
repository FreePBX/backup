<?php
namespace FreePBX\modules\Backup\Handlers;
use Symfony\Component\Filesystem\Filesystem;
abstract class CommonBase {
	protected $freepbx;
	protected $Backup;
	protected $logpath;
	protected $logger;
	protected $backupModVer;
	protected $file;
	protected $pid;
	protected $fs;
	protected $tmp;

	public function __construct($freepbx, $file, $transactionId, $pid) {
		$this->freepbx = $freepbx;
		$this->Backup = $freepbx->Backup;
		$this->logpath = $this->Backup->getConfig('logpath');
		$this->logpath = !empty($this->logpath)?$this->logpath:$this->freepbx->Config->get('ASTLOGDIR').'/backup.log';
		//Get the version of the backup module currently in use
		$this->backupModVer = (string)$this->freepbx->Modules->getInfo('backup')['backup']['version'];
		$this->transactionId = $transactionId;
		$this->fs = new Filesystem;
		$this->file = $file;
		$this->pid = $pid;
		$this->tmp = sys_get_temp_dir().'/backup/'.time();
	}

	/**
	 * Override the logger functionality
	 *
	 * @return void
	 */
	protected function setupLogger() {
		$this->logger = $this->freepbx->Logger->createLogDriver('backup', $this->logpath, \Monolog\Logger::DEBUG);
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
		switch ($logLevel) {
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