<?php

namespace FreePBX\modules\Backup\Monolog;
use Monolog\Handler\MailHandler;
use Monolog\Logger;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Swift as SwiftMailer;

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
	public function __construct(\Swift_Mailer $mailer, \Swift_Message $message, $level = Logger::DEBUG, $bubble = true, $backupInfo) {
		parent::__construct($level, $bubble);
		$this->backupInfo = $backupInfo;
		$this->mailer = $mailer;
		$this->messageTemplate = $message;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function send($content, array $records) {
		$errors = false;
		foreach ($records as $record) {
			if($record['level'] > 399){
				$errors = true;
			}
		}
		if($errors && $this->backupInfo['backup_emailtype'] != 'success' && $this->backupInfo['backup_emailtype'] != 'both'){
			return;
		}
		if(!$errors && $this->backupInfo['backup_emailtype'] != 'failure' && $this->backupInfo['backup_emailtype'] != 'both'){
			return;
		}

		$subject = sprintf(_('Backup %s success for %s'), $this->backupInfo['backup_name'], $this->backupInfo['ident']);
		if (!empty($errors)) {
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

		$message = clone $this->messageTemplate;
		$message->generateId();

		if (!$message instanceof \Swift_Message) {
			throw new \InvalidArgumentException('Could not resolve message as instance of Swift_Message or a callable returning it');
		}

		if ($records) {
			$subjectFormatter = $this->getSubjectFormatter($message->getSubject());
			$message->setSubject($subjectFormatter->format($this->getHighestRecord($records)));
		}

		$inline = (!isset($this->backupInfo['backup_emailinline']) || $this->backupInfo['backup_emailinline'] === 'no') ? false : true;
		$log_content = "\n".file_get_contents($location."/backup.log");
		if($inline) {			
			$message->setBody($content.$log_content);
		} else {
			$message->attach(new \Swift_Attachment($content.$log_content, $location.'/backup.log', 'text/plain'));
			$message->setBody(_('See attachment'));
		}

		if (version_compare(SwiftMailer::VERSION, '6.0.0', '>=')) {
			$message->setDate(new \DateTimeImmutable());
		} else {
			$message->setDate(time());
		}

		return $message;
	}
}
