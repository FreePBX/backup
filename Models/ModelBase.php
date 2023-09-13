<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 */
namespace FreePBX\modules\Backup\Models;

#[\AllowDynamicProperties]
class ModelBase {
	protected $FreePBX;
	protected $backupModVer;
	protected $data = [
		'version' => null,
		'module' => null,
		'pbx_version' => null,
		'skip_reset' => false,
		'dirs' => [],
		'files' => [],
		'configs' => [],
		'dependencies' => [],
		'garbage' => []
	];
	protected $defaultFallback = false;
	protected $getBackupManifestBackupInfo = [];
	protected $cliarguments = [];

	public function __construct($freepbx, $backupModVer, $logger, $transactionId, $modData, $defaultFallback){
		$this->FreePBX = $freepbx;
		$this->backupModVer = $backupModVer;
		$this->logger = $logger;
		$this->transactionId = $transactionId;
		$this->defaultFallback = $defaultFallback;
		$this->getBackupManifestBackupInfo = $modData['backupInfo']['backupInfo'] ?? [];
		$this->cliarguments = isset($modData['cliarguments'])? $modData['cliarguments']:array();
		foreach($this->data as $key => $data) {
			if(!isset($modData[$key])) {
				$modData[$key] = $data;
			}
		}

		$this->data = $modData;
	}

	/**
	 * Get Directories
	 *
	 * @param array $options
	 * @return array
	 */
	public function getDirs($options = []) {
		return $this->data['dirs'];
	}

	/**
	 * Get Directories Alias
	 *
	 * @param array $options
	 * @return array
	 */
	public function getDirectories($options = []) {
		return $this->getDirs($options);
	}

	/**
	 * Get Files
	 *
	 * @param array $options
	 * @return array
	 */
	public function getFiles($options = []) {
		return $this->data['files'];
	}

	/**
	 * Get Configurations
	 *
	 * @param array $options
	 * @return array
	 */
	public function getConfigs($options = []){
		return $this->data['configs'];
	}

	/**
	 * Get Module Dependencies
	 *
	 * @param array $options
	 * @return array
	 */
	public function getDependencies($options = []){
		return $this->data['dependencies'];
	}

	/**
	 * Get Extra Data
	 *
	 * @param array $options
	 * @return array
	 */
	public function getExtraData($options = []) {
		return $this->data['extradata'];
	}

	/**
	 * Get Raw Data
	 *
	 * @return array
	 */
	public function getData(){
		return $this->data;
	}

	/**
	 * Get Module Version
	 *
	 * @return string
	 */
	public function getVersion() {
		return $this->data['version'];
	}

	/**
	 * Get PBX Version
	 *
	 * @return string
	 */
	public function getPBXVersion() {
		return $this->data['pbx_version'];
	}
	/* return reset is required or not */
	public function getSkipReset() {
		return $this->data['skip_reset'];
	}
	/* Return the backupmainfest backupinfo when the module request*/
	public function getBackupInfo() {
		return $this->getBackupManifestBackupInfo;
	}

	public function getCliarguments() {
		return $this->cliarguments;
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
