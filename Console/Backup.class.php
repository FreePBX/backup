<?php
namespace FreePBX\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

use Symfony\Component\Console\Command\HelpCommand;

class Backup extends Command {

	protected function configure(){
		$this->setName('backup')
			->setDescription(_('Manage Backup jobs'))
			->setDefinition([
				new InputOption('json', null, InputOption::VALUE_NONE, _('Output in JSON')),
					new InputOption('history', null, InputOption::VALUE_NONE, _('Backup History')),
				]);
	}

	protected function execute(InputInterface $input, OutputInterface $output){
		if ($input->getOption('json')) {
			$json = true;
		} else {
			$json = false;
		}

		if ($input->getOption('history')) {
			$backups = \FreePBX::Database()->query("SELECT `id`,`manifest` FROM `backup_cache` ORDER BY `id`")->fetchAll(\PDO::FETCH_KEY_PAIR);
			$rows = [];
			foreach($backups as $id => $s) {
				$data = $this->processCache($s);
				$rows[] = [
					$id,
					$data['name'],
					$data['hostname'],
					$data['file_count'],
					$data['starttime']->format("Y-m-d H:i:s"),
					$data['endtime']->format("Y-m-d H:i:s"),
				];
			}
			if (!$json) {
				$table = new Table($output);
				$table->setHeaders([ _('Job ID'), _("Job Name"), _("Hostname"), _("File Count"), _("Start"), _("End")  ]);
				$table->setRows($rows);
				$table->render();
			} else {
				$output->write(json_encode($rows));
			}
			return;
		}
		$this->outputHelp($input,$output);
	}

	private function processCache($serdata) {
		// If there's an Object or a Class in here, don't try to unserialize it. Something's
		// wrong.
		if (preg_match("/(O:|C:)/", $serdata)) { 
			throw new \Exception("Invalid data to unserialize - $serdata");
		}
		$data = unserialize($serdata);
		$dti = new \DateTimeImmutable();
		$retarr = [
			"name" => $data['name'],
			"hostname" => $data['hostname'],
			"file_count" => $data['file_count'],
			"starttime" => $dti,
			"endtime" => $dti,
		];
		if (!empty($data['ctime'])) {
			$retarr['starttime'] = $dti->setTimestamp($data['ctime']);
		}
		if (!empty($data['ftime'])) {
			$retarr['endtime'] = $dti->setTimestamp($data['ftime']);
		}
		return $retarr;
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 * @throws \Symfony\Component\Console\Exception\ExceptionInterface
	 */
	protected function outputHelp(InputInterface $input, OutputInterface $output)	 {
		$help = new HelpCommand();
		$help->setCommand($this);
		return $help->run($input, $output);
	}
}
