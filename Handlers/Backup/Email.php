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

		$emailList = explode(',', $email);
		$logger = $this->getLogger();

		$this->freepbx->Mail->resetMessage();
		$this->freepbx->Mail->setTo($emailList);
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

	public function sendEmail($error) {
		if(!isset($this->backupInfo['backup_email']) || empty($this->backupInfo['backup_email'])){
			dbug("backup_email not set, hence not sending email..");	
			return;
		}
		if(!isset($this->backupInfo['backup_emailtype']) || empty($this->backupInfo['backup_emailtype'])){
			dbug("backup_emailtype not set, hence not sending email");	
			return;
		}
		if ($error && ( $this->backupInfo['backup_emailtype'] == 'failure' || $this->backupInfo['backup_emailtype'] == 'both')) {
			$this->attachEmailHandler();
			$this->log(sprintf(_("Generated Backup process result email to %s. Status: Failure"), $this->backupInfo['backup_email']),'DEBUG');

		} else if (!$error && ( $this->backupInfo['backup_emailtype'] == 'success' || $this->backupInfo['backup_emailtype'] == 'both')) {
			$this->attachEmailHandler();
			$this->log(sprintf(_("Generated Backup process result email to %s. Status: Success"), $this->backupInfo['backup_email']),'DEBUG');
		}
		return;
	}
}
