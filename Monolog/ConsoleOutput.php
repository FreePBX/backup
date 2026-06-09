<?php

namespace FreePBX\modules\Backup\Monolog;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Symfony\Component\Console\Output\ConsoleOutput as SymfonyConsoleOutput;

class ConsoleOutput extends AbstractProcessingHandler {
	private $queue;
	private $file;
	private $initialized = false;
	private $output;

	public function __construct($level = Logger::DEBUG, $bubble = true) {
		parent::__construct($level, $bubble);
	}

	private function getOutput() {
		try {
			$backupOutput = \FreePBX::Create()->Backup->output ?? null;
			if ($backupOutput) {
				return $backupOutput;
			}
		} catch (\Exception) {
		}
		if ($this->output === null) {
			$this->output = new SymfonyConsoleOutput();
		}
		return $this->output;
	}

	protected function write(array $record): void  {
		$output = $this->getOutput();
		switch($record['level']) {
			case Logger::EMERGENCY:
			case Logger::ALERT:
			case Logger::CRITICAL:
			case Logger::ERROR:
				$output->writeln('<error>'.$record['formatted'].'</error>');
			break;
			case Logger::WARNING:
			case Logger::NOTICE:
				$output->writeln('<comment>'.$record['formatted'].'</comment>');
			break;
			case Logger::INFO:
			case Logger::DEBUG:
			default:
				$output->writeln($record['formatted']);
			break;
		}
	}
}
