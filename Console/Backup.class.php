<?php
namespace FreePBX\Console\Command;
use FreePBX\modules\Backup\Handlers as Handler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Filesystem\LockHandler;


class Backup extends Command {
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
		))
		->setHelp('Run a backup: fwconsole backup --backup [backup-id]'.PHP_EOL
		.'Run a restore: fwconsole backup --restore [/path/to/restore-xxxxxx.tar.gz]'.PHP_EOL
		.'List backups: fwconsole backup --list'.PHP_EOL
		.'Dump remote backup string: fwconsole --dumpextern [backup-id]'.PHP_EOL
		.'Run backup job with remote string: fwconsole --externbackup [Base64encodedString]'.PHP_EOL
		.'Run backup job with remote string and custom transaction id: fwconsole --externbackup [Base64encodedString] --transaction [yourstring]'.PHP_EOL
		.'Run backup on a single module: fwconsole --backupsingle [modulename] --singlesaveto [output/path]'.PHP_EOL
		.'Run a single module backup: fwconsole --restoresingle [filename]'.PHP_EOL
		);
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$this->output = $output;
		$this->input = $input;
        $this->freepbx = \FreePBX::Create();
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
        if($backupsingle){
            $saveto = $input->getOption('singlesaveto')?$input->getOption('singlesaveto'):'';
            $job = new Handler\SingleBackup($backupsingle, $this->freepbx, $saveto);
            return $job->doSingleBackup();
        }
        if($restoresingle){
            $job = new Handler\SingleRestore($restoresingle, $this->freepbx);
            return $job->doSingleRestore();
        }
		if($input->getOption('implemented')){
			$backupHandler = new Handler\Backup($this->freepbx);
			$output->writeln(json_encode($backupHandler->getModules()));
			return;
		}
		$job = $transaction?$transaction:$this->freepbx->Backup->generateID();

		switch (true) {
			case $list:
				$this->listBackups();
			break;
			case $backup:
				$buid = $input->getOption('backup');
				$output->writeln(sprintf('Starting backup job with ID: %s',$job));
				$lockHandler = new LockHandler($job.'.'.$buid);
				if (!$lockHandler->lock()) {
					$this->log($job, _("A backup job for this id is already running"));
    			return false;
				}
				if ($warmspare) {
					$ws = new FreePBX\modules\Backup\Handlers\Warmspare($this->freepbx);
					return $ws->process($buid);
				}
				$backupHandler = new Handler\Backup($this->freepbx);
				$pid = posix_getpid();
				$errors = $backupHandler->process($buid,$job,null,$pid);
				$lockHandler->release();
			break;
			case $restore:
				$backupType = $this->freepbx->Backup->determineBackupFileType($restore);
				if($backupType === false){
					throw new \Exception('Unknown file type');
				}
				if($backupType === 'current'){
					$restoreHandler = new Handler\Restore($this->freepbx);
				}
				if($backupType === 'legacy'){
					$restoreHandler = new Handler\Legacy($this->freepbx);
				}
				$output->writeln(sprintf('Starting restore job with file: %s',$restore));
				//We don't EVER want multiple restores running.
				$lockHandler = new LockHandler('restore');
				if (!$lockHandler->lock()) {
					$this->log($job, _("A restore task is already running"));
    				return false;
				}				
				$errors = $restoreHandler->process($restore,$job,$warmspare);
				$output->writeln(sprintf('Finished restore job with file: %s',$restore));
				$lockHandler->release();
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
				$lockHandler = new LockHandler('restore');
				if (!$lockHandler->lock()) {
					$this->log($job, _("A backup task is already running"));
					return false;
				}			
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
}
