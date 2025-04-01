<?php
namespace FreePBX\modules\Backup\Handlers\Backup;

trait Email {

	private function attachEmailHandler($error,$transactionId = '') {
		$logfile = '/var/log/asterisk/backup-'.$transactionId.'.log';
		$sendemail = false;
		$lines = [];
		if(file_exists($logfile)) {
			$sysname = $this->freepbx->Config->get('FREEPBX_SYSTEM_IDENT');
			//get the backup status by log file
			$lines = file($logfile, FILE_IGNORE_NEW_LINES);
			if ($lines === false) {
				$lines = [];
			}
			if($error === true){
				$subject = sprintf(_('Backup %s failed for %s'), $this->backupInfo['backup_name'], $sysname);
				if($this->backupInfo['backup_emailtype'] == 'failure' || $this->backupInfo['backup_emailtype'] == 'both') {
					$sendemail = true;
				}
			} else {
				// Loop through the lines in reverse order
				for ($i = count($lines) - 1; $i >= 0; $i--) {
					$line = $lines[$i];
					if (stripos($line, 'Finished Saving to selected Filestore locations') !== false) {
						$subject = sprintf(_('Backup %s success for %s'), $this->backupInfo['backup_name'], $sysname);
						if($this->backupInfo['backup_emailtype'] == 'success' || $this->backupInfo['backup_emailtype'] == 'both') {
							$sendemail = true;
						}
						break;
					}
				}
			}
		} else {
			// Log file does not exist, set default subject
			$sysname = $this->freepbx->Config->get('FREEPBX_SYSTEM_IDENT');
			$subject = sprintf(_('Backup %s status for %s'), $this->backupInfo['backup_name'], $sysname);
			$sendemail = true;
		}

		if($sendemail) {
			$email = new \CI_Email();
			$emailId = $this->backupInfo['backup_email'];
			$email->set_mailtype("html");

			//Generic email
			$from = 'freepbx@freepbx.local';
			//If we have sysadmin and "from is set"
			if(function_exists('sysadmin_get_storage_email')){
				$emails = sysadmin_get_storage_email();
				//Check that what we got back above is a email address
				if(!empty($emails['fromemail']) && filter_var($emails['fromemail'],FILTER_VALIDATE_EMAIL)){
					$from = $emails['fromemail'];
				}
			}
			$from = filter_var($this->freepbx->Config->get('AMPBACKUPEMAILFROM'),FILTER_VALIDATE_EMAIL)?$this->freepbx->Config->get('AMPBACKUPEMAILFROM'):$from;
			$email->from($from,$sysname);
			$emailList = explode(',', (string) $emailId);
			$email->to($emailList);
			$email->subject($subject);

			$inline = (!isset($this->backupInfo['backup_emailinline']) || $this->backupInfo['backup_emailinline'] === 'no') ? false : true;
			if($inline && !empty($lines)) {
				//read log file and add the contents to email body
				$content = '';
				foreach ($lines as $ln) {
					$content .= "<br />".str_replace("[] []","",$ln);
				}
				$email->message($content);
			} else {
				if (!empty($lines)) {
					$email->message(_("See attachment"));
					$email->attach($logfile);
				} else {
					$email->message(_("Log file was not generated for this backup."));
				}
			}
			$email->set_priority(1);
			$email->send();
		}
		return;
	}

	public function sendEmail($error,$transactionId = '') {
		if(!isset($this->backupInfo['backup_email']) || empty($this->backupInfo['backup_email'])){
			dbug("backup_email not set, hence not sending email..");	
			return;
		}
		if(!isset($this->backupInfo['backup_emailtype']) || empty($this->backupInfo['backup_emailtype'])){
			dbug("backup_emailtype not set, hence not sending email");	
			return;
		}
		if ($error && ( $this->backupInfo['backup_emailtype'] == 'failure' || $this->backupInfo['backup_emailtype'] == 'both')) {
			$this->attachEmailHandler($error,$transactionId);
			$this->log(sprintf(_("Generated Backup process result email to %s. Status: Failure"), $this->backupInfo['backup_email']),'DEBUG');

		} else if (!$error && ( $this->backupInfo['backup_emailtype'] == 'success' || $this->backupInfo['backup_emailtype'] == 'both')) {
			$this->attachEmailHandler($error,$transactionId);
			$this->log(sprintf(_("Generated Backup process result email to %s. Status: Success"), $this->backupInfo['backup_email']),'DEBUG');
		}
		return;
	}
}
