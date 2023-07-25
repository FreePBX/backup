<?php

namespace FreePBX\modules\Backup\Monolog;
use Monolog\Handler\MailHandler;
use Monolog\Logger;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Symfony\Component\Process\Process;
use Swift as SwiftMailer;
use Swift_Message;

/**
 * SwiftMailerHandler uses Swift_Mailer to send the emails
 *
 * This version is a manipulation for Backup and allows backup settings to be passed
 *
 * @author Gyula Sallai
 * @author Andrew Nagy
 */
class Swift extends MailHandler {
	private $mailer;
	private $messageTemplate;

	/**
	 * @param \Swift_Mailer		$mailer  The mailer to use
	 * @param \Swift_Message 	$message An example message for real messages, only the body will be replaced
	 * @param int				$level   The minimum logging level at which this handler will be triggered
	 * @param Boolean			$bubble  Whether the messages that are handled can bubble up the stack or not
	 */
	public function __construct(\Swift_Mailer $mailer, \Swift_Message $message, $level = Logger::DEBUG, $bubble = true, $backupInfo = []) {
		parent::__construct($level, $bubble);
		$this->backupInfo = $backupInfo;
		$this->mailer = $mailer;
		$this->messageTemplate = $message;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function send($content, array $records): void {
		$location = \FreePBX::Config()->get('ASTLOGDIR');
		$errors = false;
		foreach ($records as $record) {
			if($record['level'] > 399){
				$errors = true;
			}
		}

		if($errors === false){
			/**
			 * double check
			 * within $content:
			 * 		Status: Failure
			 * 		Status: Success
			 */
			$errors = strpos(strtolower($content), _("success")) === false? true : false;
		}		
		
		switch($this->backupInfo['backup_emailtype']){
			case "both":
				break;
			case "success":
				if($errors === true){
					return;
				}
				break;
			case "failure":
				if($errors === false){
					return;
				}
				break;
		}

		$subject = sprintf(_('Backup %s success for %s'), $this->backupInfo['backup_name'], $this->backupInfo['ident']);
		if ($errors === true) {
			$subject = sprintf(_('Backup %s failed for %s'), $this->backupInfo['backup_name'], $this->backupInfo['ident']);
		}

		$this->messageTemplate->setSubject($subject);

		try {
			$this->mailer->send($this->buildMessage($content, $records));
		} catch(\Exception $e) {
			$nt = \FreePBX::Notifications();
			$nt->add_error('backup', 'EMAIL', _('Unable to send backup email!'), $e->getMessage(), "", true, true);
		}

	}

	/**
	 * Gets the formatter for the Swift_Message subject.
	 *
	 * @param  string			 $format The format of the subject
	 * @return FormatterInterface
	 */
	protected function getSubjectFormatter($format) {
		return new LineFormatter($format);
	}

	/**
	 * Creates instance of Swift_Message to be sent
	 *
	 * @param  string		  $content formatted email body to be sent
	 * @param  array		  $records Log records that formed the content
	 * @return \Swift_Message
	 */
	protected function buildMessage($content, array $records) {
		$location = \FreePBX::Config()->get('ASTLOGDIR');
		$message = null;

		$message = new Swift_Message(); // Create a new Swift_Message object
		$message->setSubject($this->messageTemplate->getSubject())
				->setFrom($this->messageTemplate->getFrom())
				->setTo($this->messageTemplate->getTo());

		$message->setId(uniqid());

		if (!$message instanceof \Swift_Message) {
			throw new \InvalidArgumentException('Could not resolve message as instance of Swift_Message or a callable returning it');
		}

		if ($records) {
			$subjectFormatter = $this->getSubjectFormatter($message->getSubject());
			$message->setSubject($subjectFormatter->format($this->getHighestRecord($records)));
		}

		$inline = (!isset($this->backupInfo['backup_emailinline']) || $this->backupInfo['backup_emailinline'] === 'no') ? false : true;

		/**
		 * Creating new log file and cleaning content.
		 */
		if(file_exists($location.'/backup-'.$records[0]['channel'].'.log')) {
			$log_content = str_replace("[] []","", file_get_contents($location.'/backup-'.$records[0]['channel'].'.log'));
			$log_file = "backup-".strtotime("now").".log";

			if($inline) {	
				$message->setBody($content."\n".$log_content);
			} else {
				file_put_contents("/tmp/".$log_file, $log_content);
				$f_mime = mime_content_type("/tmp/".$log_file);
				unlink("/tmp/".$log_file);

				$message->attach(new \Swift_Attachment($log_content, $log_file, $f_mime));
				$message->setBody(_('See attachment'));
			}

			//now copy the content to backup.log(backup module standard file) and delete this unique file
			$command = 'cat '.$location.'/backup-'.$records[0]['channel'].'.log >> '.$location.'/backup.log';
			$process = \freepbx_get_process_obj($command);
			$process->setTimeout(50);
			$process->mustRun();
			unlink($location.'/backup-'.$records[0]['channel'].'.log');
		}

		if (version_compare(SwiftMailer::VERSION, '6.0.0', '>=')) {
			$message->setDate(new \DateTimeImmutable());
		} else {
			$message->setDate(time());
		}



		return $message;
	}
}
