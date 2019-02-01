<?php
/**
 * Copyright Sangoma Technologies, Inc 2018
 */
namespace FreePBX\modules\Backup\Handlers\Backup;
use FreePBX\modules\Backup\Modules as Module;
use FreePBX\modules\Backup\Models as Models;
use splitbrain\PHPArchive\Tar;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use function FreePBX\modules\Backup\Json\json_decode;
use function FreePBX\modules\Backup\Json\json_encode;
use Monolog\Formatter;
class Multiple extends Common {
	private $id;
	private $dependencies = [];

	/**
	 * Constructor
	 *
	 * @param FreePBX $freepbx
	 * @param string $id
	 * @param string $transactionId
	 * @param integer $pid
	 */
	public function __construct($freepbx, $id, $transactionId, $pid) {
		$this->id = $id;
		$spooldir = $freepbx->Config->get("ASTSPOOLDIR");
		$this->backupInfo = $freepbx->Backup->getBackup($this->id);
		$underscoreName = str_replace(' ', '_', $this->backupInfo['backup_name']);
		$filePath = sprintf('%s/backup/%s',$spooldir,$underscoreName);
		parent::__construct($freepbx, $filePath, $transactionId, $pid);
	}

	/**
	 * Process the backup
	 *
	 * @return void
	 */
	public function process() {
		if(empty($this->id)){
			throw new \Exception("Backup id not provided", 500);
		}

		$errors = [];

		$this->log(sprintf(_("Running Backup ID: %s"),$this->id),'DEBUG');
		$this->log(sprintf(_("Transaction: %s"),$this->transactionId),'DEBUG');

		$this->log(sprintf(_("Starting backup %s"),$this->underscoreName),'DEBUG');

		//Use Legacy backup naming
		$tarfilename = sprintf('%s%s-%s-%s',date("Ymd-His-"),time(),getVersion(),rand());
		$targzname = sprintf('%s.tar.gz',$tarfilename);
		$this->log(_("This backup will be stored locally and is subject to maintenance settings"),'DEBUG');
		$this->log(sprintf(_("Storage Location: %s"),$this->filePath.'/'.$targzname));
		$this->setFilename($targzname);
		$tar = $this->openFile();

		//Setup the process queue
		$processQueue = new \SplQueue();
		$processQueue->setIteratorMode(\SplQueue::IT_MODE_DELETE);
		$data = [];
		$dirs = [];
		$files = [];
		$cleanup = [];
		$manifest = [
			'modules' => [],
			'skipped' => [],
			'date' => time(),
			'backupInfo' => $this->backupInfo,
		];
		$validMods = $this->getModules();
		$backupItems = $this->Backup->getAll('modules_'.$this->id);
		$selectedmods = is_array($backupItems)?array_keys($backupItems):[];
		$selectedmods = array_map('strtolower', $selectedmods);
		foreach($selectedmods as $mod) {
			if(!isset($validMods[$mod])){
				$manifest['skipped'][] = ucfirst($mod);
				$msg = sprintf(_("Could not backup module %s, it may not be installed or enabled"),$mod);
				$this->log($msg,'WARNING');
				$this->addError($msg);
				continue;
			}
			$this->dependencies[$mod] = $validMods[$mod]['version'];
			$processQueue->enqueue(['rawname' => $mod, 'ucfirst' => ucfirst($mod)]);
		}

		//Process the Queue
		foreach($processQueue as $mod) {
			$moddata = $this->processModule($mod);
			if(empty($moddata)) {
				$manifest['skipped'][] = $mod['ucfirst'];
			}
			if(!empty($moddata['dependencies'])) {
				$moddeps = array_map('strtolower', $moddata['dependencies']);
				foreach($moddeps as $depend){
					if($depend === 'framework') {
						$msg = sprintf(_("Skpping %s which %s depends on because it is a system requirement"),$depend, $mod['rawname']);
						$this->log("\t".$msg,'WARNING');
						continue;
					}
					if(!isset($validMods[$depend])) {
						$manifest['skipped'][] = ucfirst($depend);
						$msg = sprintf(_("Could not backup module %s which %s depends on, it may not be installed or enabled"),$depend, $mod['rawname']);
						$this->log("\t".$msg,'WARNING');
						$this->addWarning($msg);
						continue;
					}
					// Add the dependency to the top of the lineup
					$this->prioritizeDependency($validMods[$depend]['rawname'],$validMods[$depend]['version']);

					//If we are already backing up the module we don't need to put it in the queue If we haven't implimented backup it won't be there anyway
					if(in_array($depend, $selectedmods)){
						continue;
					}

					$this->log("\t".sprintf(_("Adding module %s which %s depends on"),$depend, $mod['name']),'DEBUG');
					$selectedmods[] = $depend;
					$processQueue->enqueue(['rawname' => $validMods[$depend]['rawname'], 'ucfirst' => ucfirst($validMods[$depend]['rawname'])]);
				}
			}

			$cleanup[$mod['ucfirst']] = $moddata['garbage'];
			$modInfo = $this->freepbx->Modules->getInfo($mod['rawname']);
			$manifest['modules'][] = ['module' => $mod['rawname'], 'version' => $modInfo[$mod['rawname']]['version']];
		}

		//Needs to look the same as ['modules']
		foreach($this->dependencies as $rawname => $version) {
			$manifest['processorder'][] = [
				'module' => $rawname,
				'version' => $version
			];
		}


		$tar->addData('metadata.json', json_encode($manifest,JSON_PRETTY_PRINT));

		$this->closeFile();

		$this->log(_("Starting Cleaning up"));
		foreach ($cleanup as $key => $items) {
			if(!empty($items)) {
				$this->log(sprintf(_("Cleaning up data generated by %s"),$key));
				foreach($items as $item) {
					$this->log("\t".sprintf(_("Removing %s"),$item),'DEBUG');
					$this->fs->remove($item);
				}
			}
		}
		$this->log(_("Finished Cleaning up"));

		$this->log(sprintf(_("Finished created backup file: %s"),$this->getFile()));

		return $this->getFile();
	}

	/**
	 * Get a list of modules that implement the backup method
	 * @return array list of modules
	 */
	private function getModules(){
		//All modules impliment the "backup" method so it is a horrible way to know
		//which modules are valid. With the autoloader we can do this magic :)
		$webrootpath = $this->freepbx->Config->get('AMPWEBROOT');
		$moduleInfo = $this->freepbx->Modules->getInfo(false,MODULE_STATUS_ENABLED);
		$validmods = [];
		foreach ($moduleInfo as $rawname => $data) {
			$bufile = $webrootpath . '/admin/modules/' . $rawname.'/Backup.php';
			if(file_exists($bufile)){
				$validmods[$rawname] = $data;
			}
		}

		return $validmods;
	}

	/**
	 * Prioritize Dependencies
	 *
	 * @param string $dependency
	 * @param boolean $version
	 * @return void
	 */
	private function prioritizeDependency($dependency,$version){
		if(isset($this->dependencies[$dependency])) {
			unset($this->dependencies[$dependency]);
		}
		$this->dependencies = [$dependency => $version] + $this->dependencies;
	}

	protected function attachEmail(){
		if(!isset($this->backupInfo['backup_email']) || empty($this->backupInfo['backup_email'])){
			return false;
		}
		if(!isset($this->backupInfo['backup_emailtype']) || empty($this->backupInfo['backup_emailtype'])){
			return false;
		}

		$serverName   = $this->freepbx->Config->get('FREEPBX_SYSTEM_IDENT');
		$emailSubject = sprintf(_("The backup %s did not set a status and may have had an error"), $this->backupInfo['backup_name']);

		$from    = $this->Backup->getConfig('fromemail');
		if(empty($from)){
			return;
		}

		$transport = \Swift_MailTransport::newInstance();
		$this->swiftmsg = \Swift_Message::newInstance();
		$this->swiftmsg->setContentType("text/html");
		$swift = \Swift_Mailer::newInstance($transport);
		$formatter = new Formatter\HtmlFormatter();
		$this->swiftmsg->setFrom($from);
		$this->swiftmsg->setSubject($emailSubject);
		$this->swiftmsg->setTo($this->backupInfo['backup_email']);
		$this->handler = new BufferHandler(new MonologSwift($swift, $this->swiftmsg, \Monolog\Logger::INFO, true, $this->backupInfo['backup_emailtype']), 0, \Monolog\Logger::INFO);
		$this->handler->SetFormatter($formatter);
		$this->logger->pushHandler($this->handler);
	}
}
