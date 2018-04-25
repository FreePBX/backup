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
				new InputOption('backup', 'bu', InputOption::VALUE_REQUIRED, 'Backup ID'),
				new InputOption('externbackup', 'ebu', InputOption::VALUE_REQUIRED, 'Base64 encoded backup job'),
				new InputOption('dumpextern', 'd', InputOption::VALUE_REQUIRED, 'Dump Base64 backup data'),
				new InputOption('transaction', 't', InputOption::VALUE_REQUIRED, 'Transaction ID for the backup'),
				new InputOption('list', 'ls', InputOption::VALUE_NONE, 'List backups'),
				new InputOption('implemented', '', InputOption::VALUE_NONE, ''),
				new InputOption('restore', 're', InputOption::VALUE_REQUIRED, 'Restore File'),
				new InputOption('manifest', 'man', InputOption::VALUE_REQUIRED, 'File Manifest'),
		))
		->setHelp('Run a backup: fwconsole backup --id=[backup-id]'.PHP_EOL
		.'Run a restore: fwconsole backup --restore=[/path/to/restore-xxxxxx.tar.gz]'.PHP_EOL
		.'List backups: fwconsole backup --list'.PHP_EOL
		.'Dump remote backup string: fwconsole --dumpextern=[backup-id]'.PHP_EOL
		.'Run backup job with remote string: fwconsole --externbackup=[Base64encodedString]'.PHP_EOL
		.'Run backup job with remote string and custom transaction id: fwconsole --externbackup=[Base64encodedString] --transaction=[yourstring]'.PHP_EOL
		);
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$this->output = $output;
		$this->input = $input;
		$this->freepbx = \FreePBX::Create();
		$backupHandler = new Handler\Backup($this->freepbx);
		$restoreHandler = new Handler\Restore($this->freepbx);
		$list = $input->getOption('list');
		$backup = $input->getOption('backup');
		$restore = $input->getOption('restore');
		$remote = $input->getOption('externbackup');
		$dumpextern = $input->getOption('dumpextern');
		$transaction = $input->getOption('transaction');
		$manifest = $input->getOption('manifest');
		if($manifest){
			return 	$output->writeln(json_encode($this->freepbx->Backup->getMetaData($manifest),\JSON_PRETTY_PRINT));
		}
		if($input->getOption('implemented')){
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
				$pid = posix_getpid();
				$backupHandler->process($buid,$job,null,$pid);
				$lockHandler->release();

			break;
			case $restore:
				$output->writeln(sprintf('Starting restore job with file: %s',$restore));
				//We don't EVER want multiple restores running.
				$lockHandler = new LockHandler('restore');
				if (!$lockHandler->lock()) {
					$this->log($job, _("A restore task is already running"));
    				return false;
				}
				$restoreHandler->process($restore,$job);
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
				$job = $transaction?$transaction:$this->freepbx->Backup->generateID();
				$output->writeln(sprintf('Starting backup job with ID: %s',$job));
				$pid = posix_getpid();
				$this->freepbx->Backup->doBackup('',$job,$input->getOption('externbackup'),$pid);
			break;
			default:
				$output->writeln($this->getHelp());
			break;
		}
	}
	public function listBackups(){
		$this->output->writeln("fwconsole backup --backup=[Backup ID]");
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
