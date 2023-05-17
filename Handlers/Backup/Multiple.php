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
	static $validModulesCache = [];

	use Email;

	/**
	 * Constructor
	 *
	 * @param FreePBX $freepbx
	 * @param string $id
	 * @param string $transactionId
	 * @param integer $pid
	 * @param array $extradata is passed from a secondparty module
	 */
	public function __construct($freepbx, $id, $transactionId, $pid,$extradata = []) {
		$this->id = $extradata['id']?$extradata['id']:$id;
		$spooldir = $freepbx->Config->get("ASTSPOOLDIR");
		$this->backupInfo = $freepbx->Backup->getBackup($this->id);
		$this->backupInfo = $extradata['backupInfo']?$extradata['backupInfo']:$this->backupInfo;
		$this->underscoreName = str_replace(' ', '_', $this->backupInfo['backup_name']);
		$filePath = sprintf('%s/backup/%s',$spooldir,$this->underscoreName);

		parent::__construct($freepbx, $filePath, $transactionId, $pid);
	}

	/**
	 * Process the backup
	 * @param array  $extenalBackupitems is passed from secondparty module
	 * @return void
	 */
	public function process($extenalBackupitems = []) {
		if(empty($this->id)){
			throw new \Exception("Backup id not provided", 500);
		}

		$this->log(sprintf(_("Running Backup ID: %s"),$this->id),'DEBUG');
		$this->log(sprintf(_("Transaction: %s"),$this->transactionId),'DEBUG');

		$this->log(sprintf(_("Starting backup %s"),$this->underscoreName),'DEBUG');

		//Use Legacy backup naming
		$tarfilename = sprintf('%s%s-%s-%s',date("Ymd-His-"),time(),getVersion(),rand());
		$targzname = sprintf('%s.tar.gz',$tarfilename);
		$this->log(_("This backup will be stored locally and is subject to maintenance settings"),'DEBUG');
		$this->log(sprintf(_("Backup File Name: %s"),$targzname));
		$this->setFilename($targzname);
		$this->setnametodb($this->transactionId,$this->id,$targzname);
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
		$validMods = $this->Backup->getModules();
		if(count($extenalBackupitems) > 1) {
			$backupItems = $extenalBackupitems;
		} else {
			$backupItems = $this->Backup->getAll('modules_'.$this->id);
		}
		$selectedmods = is_array($backupItems)?array_keys($backupItems):[];
		$selectedmods = array_map('strtolower', $selectedmods);
		foreach($selectedmods as $mod) {
			if(!isset($validMods[$mod])){
				$manifest['skipped'][] = ucfirst($mod);
				$msg = sprintf(_("Could not backup module %s because it is not enabled"),$mod);
				$this->log($msg,'WARNING');
				$this->addWarning($msg);
				continue;
			}
			$this->dependencies[$mod] = $validMods[$mod]['version'];
			$processQueue->enqueue(['rawname' => $mod, 'ucfirst' => ucfirst($mod)]);
		}
		if($processQueue->isEmpty() && empty($this->backupInfo['custom_files'])) {
			$msg = _("No Module or Custom files selected for this backup");
			$this->log($msg,'WARNING');
			$this->addWarning($msg);
			return false;
		}

		//Process the Queue
		if(!$processQueue->isEmpty()) {
			foreach($processQueue as $mod) {
				$moddata = $this->processModule($this->id, $mod);
				if(empty($moddata)) {
					$manifest['skipped'][] = $mod['ucfirst'];
				}
				if(!empty($moddata['dependencies'])) {
					$moddeps = array_map('strtolower', $moddata['dependencies']);
					foreach($moddeps as $depend){
						if(empty($depend)) {
							$msg = sprintf(_("Depend field was blank for %s. Skipping because not sure what to do"), $mod['rawname']);
							$this->log("\t".$msg,'WARNING');
							continue;
						}
						if($depend === 'framework') {
							$msg = sprintf(_("Skpping %s which depends on framework because framework is a system requirement. Framework should be removed as a dependency"), $mod['rawname']);
							$this->log("\t".$msg,'WARNING');
							continue;
						}
						if(!isset($validMods[$depend])) {
							$manifest['skipped'][] = ucfirst($depend);
							$msg = sprintf(_("Could not backup module %s because it depends on %s which is not enabled. Please enable %s"),$mod['rawname'], $depend, $depend);
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

						$this->log("\t".sprintf(_("Adding module %s to queue because %s depends on it"),$depend, $mod['rawname']),'DEBUG');
						$selectedmods[] = $depend;
						$processQueue->enqueue(['rawname' => $validMods[$depend]['rawname'], 'ucfirst' => ucfirst($validMods[$depend]['rawname'])]);
					}
				}

				if(!empty($moddata['garbage'])) {
					$cleanup[$mod['ucfirst']] = $moddata['garbage'];
				}
				$modInfo = $this->freepbx->Modules->getInfo($mod['rawname']);
				$manifest['modules'][] = ['module' => $mod['rawname'], 'version' => $modInfo[$mod['rawname']]['version']];
			}
		}

		//Needs to look the same as ['modules']
		foreach($this->dependencies as $rawname => $version) {
			$manifest['processorder'][] = [
				'module' => $rawname,
				'version' => $version
			];
		}
		if(!empty($this->backupInfo['custom_files'])) {
			$this->addcustomFiles($this->backupInfo['custom_files']);
		}
		//FREEPBX-20060 restapps stopping the restore in between, because apache restart
		//putting restapps to end of the process order
		$processorder = $manifest['processorder'];
		unset($manifest['processorder']);
		$ivr = false;
		$queue = false;
		if(!empty($processorder)) {
			foreach ($processorder as $key => $order) {
				if($order['module'] == 'ivr'){
					$ivr = true;
					$ivrkey = $key;
				}
				if($order['module'] == 'queues'){
					$queue = true;
					$qkey = $key;
				}
				if($order['module'] == 'restapps'){
					$lastentry = $order;
				} else {
					$rearragedorder[] = $order;
				}
			}
			if(($ivr && $queue) && $qkey < $ivrkey) {// we need to process IVR before Queue
				$qu = $rearragedorder[$qkey];
				$ivr = $rearragedorder[$ivrkey];
				//swap the values
				$rearragedorder[$qkey] = $ivr;
				$rearragedorder[$ivrkey] = $qu;
			}
			if(isset($lastentry) && is_array($lastentry)) {
				$rearragedorder[] = $lastentry;
			}
			$manifest['processorder'] = $rearragedorder;
		}
		$tar->addData('metadata.json', json_encode($manifest, JSON_PRETTY_PRINT));

		$this->closeFile();

		$this->log(_("Starting Cleaning up"));
		foreach ($cleanup as $key => $items) {
			if(!empty($items)) {
				$this->log(sprintf(_("Cleaning up data generated by %s"),$key));
				foreach($items as $item) {
					$this->log("\t".sprintf(_("Removing %s"),$item),'DEBUG');
					try {
						$this->fs->remove($item);
					} catch (\Exception $e) {
						$this->log("\t".sprintf(_("Error Removing %s, %s"),$item, $e->getMessage()),'WARNING');
					}
				}
			}
		}
		$this->log(_("Finished Cleaning up"));

		$this->log(sprintf(_("Finished created backup file: %s"),$targzname));

		return $this->getFile();
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
