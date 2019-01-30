<?php
namespace FreePBX\Console\Command;
use FreePBX\modules\Backup\Handlers as Handler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Command\LockableTrait;
use function FreePBX\modules\Backup\Json\json_decode;
use function FreePBX\modules\Backup\Json\json_encode;
class Backup extends Command {
	use LockableTrait;

	protected function configure(){
		$this->setName('backup')
		->setAliases(array('bu'))
		->setDescription('Run backup and restore jobs')
		->setDefinition(array(
				new InputOption('backup', '', InputOption::VALUE_REQUIRED, 'Backup ID'),
				new InputOption('externbackup', '', InputOption::VALUE_REQUIRED, 'Base64 encoded backup job'),
				new InputOption('dumpextern', '', InputOption::VALUE_REQUIRED, 'Dump Base64 backup data'),
				new InputOption('transaction', '', InputOption::VALUE_REQUIRED, 'Transaction ID for the backup'),
				new InputOption('list', '', InputOption::VALUE_NONE, 'List backups'),
				new InputOption('warmspare', '', InputOption::VALUE_NONE, 'Set the warmspare flag'),
				new InputOption('implemented', '', InputOption::VALUE_NONE, ''),
				new InputOption('restore', '', InputOption::VALUE_REQUIRED, 'Restore File'),
				new InputOption('restoresingle', '', InputOption::VALUE_REQUIRED, 'Module backup to restore'),
				new InputOption('backupsingle', '', InputOption::VALUE_REQUIRED, 'Module to backup'),
				new InputOption('singlesaveto', '', InputOption::VALUE_REQUIRED, 'Where to save the single module backup.'),
				new InputOption('b64import', '', InputOption::VALUE_REQUIRED, ''),
		))
		->setHelp('Run a backup: fwconsole backup --backup [backup-id]'.PHP_EOL
		.'Run a restore: fwconsole backup --restore [/path/to/restore-xxxxxx.tar.gz]'.PHP_EOL
		.'List backups: fwconsole backup --list'.PHP_EOL
		.'Dump remote backup string: fwconsole backup --dumpextern [backup-id]'.PHP_EOL
		.'Run backup job with remote string: fwconsole backup --externbackup [Base64encodedString]'.PHP_EOL
		.'Run backup job with remote string and custom transaction id: fwconsole backup --externbackup [Base64encodedString] --transaction [yourstring]'.PHP_EOL
		.'Run backup on a single module: fwconsole backup --backupsingle [modulename] --singlesaveto [output/path]'.PHP_EOL
		.'Run a single module backup: fwconsole backup --restoresingle [filename]'.PHP_EOL
		);
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$this->freepbx = \FreePBX::Create();

		if(posix_getuid() === 0) {
			$AMPASTERISKWEBUSER = $this->freepbx->Config->get('AMPASTERISKWEBUSER');
			$info = posix_getpwnam($AMPASTERISKWEBUSER);
			if(empty($info)) {
				$output->writeln("$AMPASTERISKWEBUSER is not a valid user");
				return 0;
			}
			posix_setuid($info['uid']);
		}

		if (!$this->lock()) {
			$output->writeln('The command is already running in another process.');
			return 0;
		}

		$this->output = $output;
		$this->input = $input;
		$this->freepbx->Backup->output = $output;
		$list = $input->getOption('list');
		$warmspare = $input->getOption('warmspare');
		$backup = $input->getOption('backup');
		$restore = $input->getOption('restore');
		$remote = $input->getOption('externbackup');
		$dumpextern = $input->getOption('dumpextern');
		$transaction = $input->getOption('transaction');
        $backupsingle = $input->getOption('backupsingle');
        $restoresingle = $input->getOption('restoresingle');
		$b64import = $input->getOption('b64import');
		if($b64import){
			return $this->addBackupByString($b64import);
		}

		if($input->getOption('implemented')){
			$backupHandler = new Handler\Backup($this->freepbx);
			$output->writeln(json_encode($backupHandler->getModules()));
			return;
		}

		$jobid = $transaction?$transaction:$this->freepbx->Backup->generateID();

		switch (true) {
			case $backupsingle:
				$saveto = $input->getOption('singlesaveto')?$input->getOption('singlesaveto'):'';
				$job = new Handler\SingleBackup($this->freepbx, $backupsingle, $saveto, $jobid);
				return $job->process();
			break;
			case $restoresingle:
				$job = new Handler\SingleRestore($this->freepbx, $restoresingle, $jobid);
				return $job->process();
			break;
			case $list:
				$this->listBackups();
			break;
			case $backup:
				$buid = $input->getOption('backup');
				$output->writeln(sprintf('Starting backup job with ID: %s',$jobid));
				if ($warmspare) {
					$ws = new Handler\Warmspare($this->freepbx, $buid, $jobid, posix_getpid());
					return $ws->process();
				}
				$backupHandler = new Handler\Backup($this->freepbx, $buid, $jobid, posix_getpid());
				$errors = $backupHandler->process();
			break;
			case $restore:
				$backupType = $this->freepbx->Backup->determineBackupFileType($restore);
				if($backupType === false){
					throw new \Exception('Unknown file type');
				}
				$pid = posix_getpid();
				if($backupType === 'current'){
					$restoreHandler = new Handler\Restore($this->freepbx,$restore,$job);
				}
				if($backupType === 'legacy'){
					$restoreHandler = new Handler\Legacy($this->freepbx,$restore,$job);
				}
				$output->writeln(sprintf('Starting restore job with file: %s',$restore));
				$errors = $restoreHandler->process($warmspare);
				$output->writeln(sprintf('Finished restore job with file: %s',$restore));
			break;
			case $dumpextern:
				$backupdata = $this->freepbx->Backup->getBackup($input->getOption('dumpextern'));
				if(!$backupdata){
					$output->writeln("Could not find the backup specified please check the id.");
					return false;
				}
				$backupdata['backup_items'] = $this->freepbx->Backup->getAll('modules_'.$input->getOption('dumpextern'));
				$output->writeln(base64_encode(json_encode($backupdata)));
				return true;
			break;
			case $remote:
				$job = $transaction?$transaction:$this->freepbx->Backup->generateID();
				$output->writeln(sprintf('Starting backup job with ID: %s',$job));
				$pid = posix_getpid();
				$errors  = $backupHandler->process('',$job,$input->getOption('externbackup'),$pid);
			break;
			default:
				$output->writeln($this->getHelp());
			break;
		}
		if(!empty($errors) && OutputInterface::VERBOSITY_VERY_VERBOSE){
			$output->writeln(implode(PHP_EOL,$errors));
		}

	}
	public function listBackups(){
		$this->output->writeln("fwconsole backup --backup [Backup ID]");
		$table = new Table($this->output);
		$table->setHeaders(['Backup Name','Description','Backup ID']);
		$list = [];
		foreach ($this->freepbx->Backup->listBackups() as $value) {
			$list[] = [$value['name'],$value['description'],$value['id']];
		}
		$table->setRows($list);
		$table->render();
	}

	public function addBackupByString($base64){
		$data = json_decode(base64_decode($base64), true);
		if(json_last_error() !== JSON_ERROR_NONE){
			$this->output->writeln(sprintf('Backup could not be imorted: %s',json_last_error_msg()));
			return false;
		}
		$items = [];
		if(isset($data['backup_items'])){
			$items = $data['backup_items'];
			unset($data['backup_items']);
		}
		$id = $this->freepbx->Backup->generateID();

		foreach($data as $key => $value){
			$this->freepbx->Backup->updateBackupSetting($id,$key,$value);
		}
		$this->freepbx->Backup->setModulesById($id, $items);
		$this->freepbx->Backup->setConfig($id, array('id' => $id, 'name' => $data['backup_name'], 'description' => $data['backup_description']), 'backupList');
		$this->output->writeln(sprintf('Backup created ID: %s', $id));
		return $id;
	}
}
