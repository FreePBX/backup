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
		$this->output = new SymfonyConsoleOutput();
	}

	protected function write(array $record) {
		switch($record['level']) {
			case Logger::EMERGENCY:
			case Logger::ALERT:
			case Logger::CRITICAL:
			case Logger::ERROR:
				$this->output->writeln('<error>'.$record['formatted'].'</error>');
			break;
			case Logger::WARNING:
			case Logger::NOTICE:
				$this->output->writeln('<comment>'.$record['formatted'].'</comment>');
			break;
			case Logger::INFO:
			case Logger::DEBUG:
			default:
				$this->output->writeln($record['formatted']);
			break;
		}
	}
}