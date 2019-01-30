<?php

namespace FreePBX\modules\Backup\Monolog;
use Monolog\Handler\SwiftMailerHandler;
use Monolog\Logger;

/**
 * SwiftMailerHandler uses Swift_Mailer to send the emails
 *
 * @author Gyula Sallai
 */
class Swift extends SwiftMailerHandler {

	/**
	 * @param \Swift_Mailer           $mailer  The mailer to use
	 * @param callable|\Swift_Message $message An example message for real messages, only the body will be replaced
	 * @param int                     $level   The minimum logging level at which this handler will be triggered
	 * @param Boolean                 $bubble  Whether the messages that are handled can bubble up the stack or not
	 */
	public function __construct(\Swift_Mailer $mailer, $message, $level = Logger::ERROR, $bubble = true, $notifyType = null) {
		parent::__construct($mailer, $message, $level, $bubble);
		$this->notifyType = $notifyType;
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
		if($errors && $this->notifyType != 'success' && $this->notifyType != 'both'){
			return;
		}
		if(!$errors && $this->notifyType != 'failure' && $this->notifyType != 'both'){
			return;
		}
		parent::send($content, $records);
	}
}
