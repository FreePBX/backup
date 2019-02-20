<?php
namespace FreePBX\modules\Backup\Handlers\Backup;

use FreePBX\modules\Backup\Monolog\SwiftMailerHandler;
use Monolog\Handler\BufferHandler;
use FreePBX\modules\Backup\Monolog\Swift;
use Monolog\Formatter as Formatter;

trait Email {

	private function attachEmailHandler() {
		$email = $this->backupInfo['backup_email'];

		if(empty($email)) {
			return;
		}

		$logger = $this->getLogger();

		$this->freepbx->Mail->resetMessage();
		$this->freepbx->Mail->setTo([$email]);
		$this->freepbx->Mail->setSubject("Test");

		$settings = $this->backupInfo;
		$settings['ident'] = $this->freepbx->Config->get('FREEPBX_SYSTEM_IDENT');

		$handler = new Swift($this->freepbx->Mail->getMailer(), $this->freepbx->Mail->getMessage(), \Monolog\Logger::DEBUG, true, $settings);
		$dateFormat = "Y-M-d H:i:s";
		$output = "%message%\n";
		$formatter = new Formatter\LineFormatter($output, $dateFormat, true);
		$handler->setFormatter($formatter);

		$handler = new BufferHandler($handler);

		$logger->pushHandler($handler);
	}
}