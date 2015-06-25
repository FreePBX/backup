<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//
$dir = dirname(__FILE__);
require_once($dir . '/functions.inc/class.backup.php');
require_once($dir . '/functions.inc/backup.php');
require_once($dir . '/functions.inc/servers.php');
require_once($dir . '/functions.inc/templates.php');
require_once($dir . '/functions.inc/restore.php');
require_once($dir . '/functions.inc/s3.php');

/**
* do variable substitution 
*/
function backup__($var) {
	global $amp_conf;
	/*
	 * Substitues Config vars for __VARNAME__.
	 *
	 * If no __VAR__, return $var
	 * If Config var doesn't exist, throws an exception.
	 */
	
	if (!preg_match("/__(.+)__/", $var, $out)) {
		return $var;
	}

	$ampvar = $out[1];
	if (!\FreePBX::Config()->conf_setting_exists($ampvar)) {
		if (isset($amp_conf[$ampvar])) {
			// This is for things like AMPDBHOST which are defined in /etc/freepbx.conf
			$replace = $amp_conf[$ampvar];
		} else {
			throw new \Exception("Was asked for FreePBX Setting '$var', but it doesn't exist. Can't continue.");
		}
	} else {
		$replace = \FreePBX::Config()->get($ampvar);
	}

	return str_replace("__${ampvar}__", $replace, $var);
}


function backup_log($msg) {
	$tmp = (function_exists('sys_get_temp_dir')) ? sys_get_temp_dir() : '/tmp';
	$cli = php_sapi_name() == 'cli' ? true : false;
	$str = '';
	$str .= $cli ? '' : 'data: ';
	$str .= $msg;
	$str .= $cli ? "\n" : "\n\n";
	echo $str;
	$logmsg = date("F j, Y, g:i a").' - '. $str;
	file_put_contents($tmp.'/backup.log', trim($logmsg)."\r\n", FILE_APPEND);
	if (!$cli) {
		ob_flush();
		flush();
	}
	
}

function backup_email_log($to, $from, $subject) {
	$tmp = (function_exists('sys_get_temp_dir')) ? sys_get_temp_dir() : '/tmp';
	$email_options = array('useragent' => 'freepbx', 'protocol' => 'mail');
	$email = new CI_Email();
	$msg[] = _('BACKUP LOG ATTACHED');
	$email->from($from);
	$email->to($to);
	$email->subject(_('Backup Log:') . $subject);
	$email->message(implode("\n", $msg));
	$email->attach($tmp.'/backup.log');
	$email->send();
	
	unset($msg);
}

function backup_clear_log() {
	$tmp = (function_exists('sys_get_temp_dir')) ? sys_get_temp_dir() : '/tmp';
	$fh = fopen($tmp.'/backup.log', 'w');
	fclose($fh);
}

