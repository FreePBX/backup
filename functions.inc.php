<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

/* This file is part of FreePBX.
 * 
 *     FreePBX is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation, either version 2 of the License, or
 *     (at your option) any later version.
 * 
 *     FreePBX is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *     GNU General Public License for more details.
 * 
 *     You should have received a copy of the GNU General Public License
 *     along with FreePBX.  If not, see <http://www.gnu.org/licenses/>.

 */

$dir = dirname(__FILE__);
require_once($dir . '/functions.inc/class.backup.php');
require_once($dir . '/functions.inc/backup.php');
require_once($dir . '/functions.inc/servers.php');
require_once($dir . '/functions.inc/templates.php');
require_once($dir . '/functions.inc/restore.php');


/**
* do variable substitution 
*/
function backup__($var) {
	global $amp_conf;
	/*
	 * substitution string can look like: __STRING__
	 * find the two parimiter positions and search $this->amp_conf for the stringg
	 * return the origional string if substitution is not found
	 *
	 * for now, ONLY MATCHES UPERCASE in both $var and amp_conf
	 */
	
	//get first position
	$pos1 = strpos($var, '__');
	if ($pos1 === false) {
		return $var;
	}
	
	//get second position
	$pos2 = strpos($var, '__', $pos1 + 2);
	if ($pos2 === false) {
		return $var;
	}
	
	//get actual string, sans _'s
	$v = trim(substr($var, $pos1, $pos2 + 2), '_');

	//return a value if we have match, otherwise the origional string
	if (isset($amp_conf[$v])) {
		return str_replace('__' . $v . '__', $amp_conf[$v], $var);
	} else {
		return $var;
	}
}


function backup_log($msg) {
	$cli = php_sapi_name() == 'cli' ? true : false;
	
	$str = '';
	$str .= $cli ? '' : 'data: ';
	$str .= $msg;
	$str .= $cli ? "\n" : "\n\n";
	
	echo $str;
	
	if (!$cli) {
		ob_flush();
		flush();
	}
	
}
?>
