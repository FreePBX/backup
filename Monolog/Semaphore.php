<?php

namespace FreePBX\modules\Backup\Monolog;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class Semaphore extends AbstractProcessingHandler {
	private $queue;
	private $file;
	private $initialized = false;

	public function __construct($file, $level = Logger::DEBUG, bool $bubble = true) {
		$this->file = $file;
		parent::__construct($level, $bubble);
	}

	private function initialize() {
		$key = ftok($this->file, 'A');
		$this->queue = msg_get_queue($key);
	}

	protected function write(array $record) {
		if (!$this->initialized) {
			$this->initialize();
		}
		msg_send($this->queue, $record['level'], $record['formatted']);
	}
}