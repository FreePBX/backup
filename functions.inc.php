<?php

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

/************ LEGACY FUNCTION ****************/
function backup_list_files($dir='', $display='', $file='') {
	global $type,$asterisk_conf,$amp_conf;
	$html='';
	if(is_dir($dir)){
		if(($file!=".") && ($file!="..") && ($file!="")){
			$html.="<li><a class=\"info\" href=\"javascript:decision('"._("Are you sure you want to delete this File Set?")."','config.php?type=$type&display=$display&action=deletedataset&dir=$dir')\">";
			$html.=_("DELETE ALL THE DATA IN THIS SET").'<span>'._("Delete this backup set and all data associated with this backup set..").'</span></a><br></li><br>';
		}
		if($dh = opendir($dir)){
			while(($file = readdir($dh))!== false){
				$file_arr[]=$file;
			}
			rsort($file_arr);
			$count=25;
			foreach($file_arr as $file){
				if(($file!=".") && ($file!="..") && ($dir==$amp_conf['ASTVARLIBDIR']."/backups/")){
					$html.="<li><a href=\"config.php?type=$type&display=$display&action=restore&dir=$dir/$file\">$file</a><br></li>";
					$count--;
				}elseif(($file!=".") && ($file!="..")){
					$html.="<li><a href=\"config.php?type=$type&display=$display&action=restore&dir=$dir/$file&file=$file\">$file</a><br></li>";
					$count--;
				}
			}
			closedir($dh);
			for ($i = $count; $i > 0; $i--){
				$html.='<br />';
			}
		}
	}elseif(substr($dir, -6)=="tar.gz" ){
		$html.="<li><a class=\"info\" href=\"javascript:decision('"._("Are you sure you want to delete this File Set?")."','config.php?type=$type&display=$display&action=deletefileset&dir=$dir&file=$file')\">";
		$html.=_("Delete File Set").'<span>'._("Delete this backup set.").'</span></a><br></li><br>';
		$tar_string="tar tfz \"$dir\" | cut -d'/' -f4";
		exec($tar_string,$restore_files,$error);
		$html.="<li><a class=\"info\" href=\"javascript:decision('"._("Are you sure you want to restore this COMPLETE file set?\\nDoing so will permanently over-write all FreePBX and Asterisk files\\nYou will lose all Your Call Detail Records and any Voicemail that was recorded between the BACKUP DATE and NOW!")."','config.php?type=$type&display=$display&action=restored&dir=$dir&filetype=ALL&file=$file')\">";
		$html.=_("Restore Entire Backup Set").'<span>'; 
		$html.=_("Restore your Complete Backup set overwriting all files.").'</span></a><br></li><br>';
		if(array_search('voicemail.tar.gz',$restore_files)){
			$html.="<li><a class=\"info\" href=\"javascript:decision('"._("Are you sure you want to Restore this file set?\\nDoing so will permanently delete any new Voicemail you have in your mailbox\\nsince this backup on")." $file!','config.php?type=$type&display=$display&action=restored&dir=$dir&filetype=VoiceMail&file=$file')\">";
			$html.=_('Restore Voicemail Files').'<span>'; 
			$html.=_('Restore your Voicemail files from this backup set.  NOTE! This will delete any Voicemail currently in the Voicemail boxes.');
			$html.='</span></a><br></li><br>';
		}
		if(array_search('recordings.tar.gz',$restore_files)){
			$html.="<li><a class=\"info\" href=\"javascript:decision('"._("Are you sure you want to Restore this file set?\\nNOTE! This will OVERWRITE any voicerecordings currently on the system. It will NOT delete new files not currently in the backup set")."','config.php?type=$type&display=$display&action=restored&dir=$dir&filetype=Recordings&file=$file')\">";
			$html.=_('Restore System Recordings Files').'<span>'; 
			$html.=_("Restore your system Voice Recordings including AutoAttendant files from this backup set.  NOTE! This will OVERWRITE any voicerecordings  currently on the system. It will NOT delete new files not currently in the backup set").'</span></a><br></li><br>';
		}
		if(array_search('configurations.tar.gz',$restore_files)){
			$html.="<li><a class=\"info\" href=\"javascript:decision('"._("Are you sure you want to Restore this File Set?\\nDoing so will Permanently Over-Write all FreePBX and Asterisk Files!")."','config.php?type=$type&display=$display&action=restored&dir=$dir&filetype=Configurations&file=$file')\">";
			$html.=_("Restore System Configuration").'<span>'._("Restore your system configuration from this backup set.  NOTE! This will OVERWRITE any System changes you have made since this backup... ALL items will be reset to what they were at the time of this backup set..").'</span></a><br></li><br>';
		}
		if(array_search('fop.tar.gz',$restore_files)){
			$html.="<li><a class=\"info\" href=\"javascript:decision('"._("Are you sure you want to Restore the Operator Panel Files?\\nDoing so will Permanently Over-Write all Operator Panel Files!")."','config.php?type=$type&display=$display&action=restored&dir=$dir&filetype=FOP&file=$file')\">";
			$html.=_("Restore Operator Panel").'<span>'; 
			$html.=_('Restore the Operator Panel from this backup set.  NOTE! This will OVERWRITE any Operator Panel Changes you have made since this backup... ALL items will be reset to what they were at the time of this backup set..').'</span></a><br></li><br>';
		}
		if(array_search('cdr.tar.gz',$restore_files)){
			$html.="<li><a class=\"info\" href=\"javascript:decision('"._("Are you sure you want to Restore the CALL DETAIL FILES?\\nDoing so will Permanently DELETE all CALL RECORDS!")."','config.php?type=$type&display=$display&action=restored&dir=$dir&filetype=CDR&file=$file')\">";
			$html.=_("Restore Call Detail Report").'<span>'; 
			$html.=_("Restore the Call Detail Records from this backup set.  NOTE! This will DELETE ALL CALL RECORDS that have been saved since this backup set."); 
			$html.='</span></a><br></li><br>';
		}
	}else{
		$html.='<h2>'._("ERROR its not a BACKUP SET file").'</h2>';
	}
	echo $html;
}

function backup_errors(&$error_cause, $ret, $cause) {
  if ($ret) {
    $error_cause[] = array('ret' => $ret, 'description' => $cause);
  }
}

function backup_restore_tar($dir="", $file="",$filetype="", $display="") {
	global $asterisk_conf, $amp_conf, $astman;
	$tar='/bin/tar';
	$error_cause = array();
	if($amp_conf['AMPBACKUPSUDO']==true){$sudo='/usr/bin/sudo';}else{$sudo='';}//use sudo if requested - DANGEROUS!!!
	switch($filetype){
		case 'ALL':
			$fileholder=substr($file, 0,-7);
			exec("/bin/rm -rf /tmp/ampbackups.$fileholder 2>&1",$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to remove previous backup set'));
	
			// First restore voicemial (for some reason if you do it all at once these don't get restored
			exec('/bin/rm -rf '.$amp_conf['ASTSPOOLDIR'].'/voicemail 2>&1',$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to remove Voicemail directory'));
			$tar_cmd="$tar -PxvOz -f \"$dir\" /tmp/ampbackups.$fileholder/voicemail.tar.gz | $tar -Pxvz";
			exec($tar_cmd,$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to untar voicemail.tar.gz'));
	
			// Next, recordings cause same issue as above
			$tar_cmd="$tar -PxvOz -f \"$dir\" /tmp/ampbackups.$fileholder/recordings.tar.gz | $tar -Pxvz";
			exec($tar_cmd,$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to untar recordings.tar.gz'));
	
			// Now the rest and then we'll get on with the databases
			$tar_cmd="$tar -PxvOz -f \"$dir\" /tmp/ampbackups.$fileholder/configurations.tar.gz | $tar -Pxvz";
			exec($tar_cmd,$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to untar configurations.tar.gz'));
			$tar_cmd="$tar -PxvOz -f \"$dir\" /tmp/ampbackups.$fileholder/fop.tar.gz /tmp/ampbackups.$fileholder/cdr.tar.gz  | $tar -Pxvz";
			exec($tar_cmd,$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to untar fop.tar.gz and cdr.tar.gz'));
			$tar_cmd="$tar -Pxvz -f \"$dir\" /tmp/ampbackups.$fileholder/asterisk.sql /tmp/ampbackups.$fileholder/asteriskcdr.sql /tmp/ampbackups.$fileholder/astdb.dump";
			exec($tar_cmd,$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to untar astdb.dump, asterisk.sql and asteriskcdr.sql'));
	
			$sql_cmd="mysql -u ".$amp_conf['AMPDBUSER']." -p".$amp_conf['AMPDBPASS']." < /tmp/ampbackups.$fileholder/asterisk.sql 2>&1";
			exec($sql_cmd,$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to untar asterisk.sql'));
			$sql_cmd="mysql -u ".$amp_conf['AMPDBUSER']." -p".$amp_conf['AMPDBPASS']." < /tmp/ampbackups.$fileholder/asteriskcdr.sql 2>&1";
			exec($sql_cmd,$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to restore asteriskcdr.sql'));
			exec($amp_conf['AMPBIN']."/restoreastdb.php $fileholder 2>&1",$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to restore the astdb'));
			
			//restore additional file (aka AMPPROVROOT), using sudo if requested
			$tar_cmd="$sudo $tar -PxvOz -f \"$dir\" /tmp/ampbackups.$fileholder/phoneconfig.tar.gz |$sudo $tar -Pxvz";
			exec($tar_cmd,$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to untar phoneconfig.tar.gz'));
			exec("/bin/rm -rf /tmp/ampbackups.$fileholder",$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to remove exploded backup sets from tmp'));
			$astman->Command('module reload manager');
			if(!count($error_cause)){$message=_("Restored All Files in Backup Set");}
			
			break;
		case 'VoiceMail':
			$fileholder=substr($file, 0,-7);
			exec("/bin/rm -rf /tmp/ampbackups.$fileholder 2>&1",$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to remove previous backup set'));
			exec('/bin/rm -rf '.$amp_conf['ASTSPOOLDIR'].'/voicemail 2>&1',$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to remove voicemail directory'));
			$tar_cmd="$tar -PxvOz -f \"$dir\" /tmp/ampbackups.$fileholder/voicemail.tar.gz | $tar -Pxvz";
			exec($tar_cmd,$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to untar voicemail.tar.gz'));
			exec("/bin/rm -rf /tmp/ampbackups.$fileholder 2>&1",$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to remove exploded backup sets from tmp'));
			if(!count($error_cause)){$message=_("Restored Voicemail");}
		break;
		case 'Recordings':
			$fileholder=substr($file, 0,-7);
			exec("/bin/rm -rf /tmp/ampbackups.$fileholder 2>&1",$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to remove previous backup set'));
			$tar_cmd="$tar -PxvOz -f \"$dir\" /tmp/ampbackups.$fileholder/recordings.tar.gz | $tar -Pxvz";
			exec($tar_cmd,$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to untar recordings.tar.gz'));
			exec("/bin/rm -rf /tmp/ampbackups.$fileholder 2>&1",$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to remove exploded backup sets from tmp'));
			if(!count($error_cause)){$message=_("Restored System Recordings");}
		break;
		case 'Configurations':
			$fileholder=substr($file, 0,-7);
			exec("/bin/rm -rf /tmp/ampbackups.$fileholder 2>&1",$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to remove previous backup set'));
			$tar_cmd="$tar -PxvOz -f \"$dir\" /tmp/ampbackups.$fileholder/configurations.tar.gz | $tar -Pxvz";
			exec($tar_cmd,$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to untar configurations.tar.gz'));
			$tar_cmd="$$tar -Pxvz -f \"$dir\" /tmp/ampbackups.$fileholder/asterisk.sql /tmp/ampbackups.$fileholder/astdb.dump";
			exec($tar_cmd,$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to untar asterisk.sql and astdb.dump'));
			$sql_cmd="mysql -u ".$amp_conf['AMPDBUSER']." -p".$amp_conf['AMPDBPASS']." < /tmp/ampbackups.$fileholder/asterisk.sql";
			exec($sql_cmd,$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to restore asterisk.sql'));
			exec($amp_conf['AMPBIN']."/restoreastdb.php $fileholder 2>&1",$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to restore the astdb'));
			//restore additional file (aka AMPPROVROOT), using sudo if requested
			$tar_cmd="$sudo $tar -PxvOz -f \"$dir\" /tmp/ampbackups.$fileholder/phoneconfig.tar.gz |$sudo $tar -Pxvz";
			exec($tar_cmd,$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to untar phoneconfig.tar.gz'));
			
			exec("/bin/rm -rf /tmp/ampbackups.$fileholder 2>&1",$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to remove exploded backup sets from tmp'));
			$astman->Command('module reload manager');
			if(!count($error_cause)){$message=_("Restored System Configuration");}
		break;
		case 'FOP':
			$fileholder=substr($file, 0,-7);
			exec("/bin/rm -rf /tmp/ampbackups.$fileholder 2>&1",$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to remove previous backup set'));
			$tar_cmd="$tar -PxvOz -f \"$dir\" /tmp/ampbackups.$fileholder/fop.tar.gz | $tar -Pxvz";
			exec($tar_cmd,$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to untar fop.tar.gz'));
			exec("/bin/rm -rf /tmp/ampbackups.$fileholder 2>&1",$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to remove exploded backup sets from tmp'));
			if(!count($error_cause)){$message=_("Restored Operator Panel");}
		break;
		case 'CDR':
			$fileholder=substr($file, 0,-7);
			exec("/bin/rm -rf /tmp/ampbackups.$fileholder 2>&1",$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to remove previous backup set'));
			$tar_cmd="$tar -PxvOz -f \"$dir\" /tmp/ampbackups.$fileholder/cdr.tar.gz | $tar -Pxvz";
			exec($tar_cmd,$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to untar cdr.tar.gz'));
			$tar_cmd="$tar -Pxvz -f \"$dir\" /tmp/ampbackups.$fileholder/asteriskcdr.sql";
			exec($tar_cmd,$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to untar asteriskcdr.sql'));
			$sql_cmd="mysql -u ".$amp_conf['AMPDBUSER']." -p".$amp_conf['AMPDBPASS']." < /tmp/ampbackups.$fileholder/asteriskcdr.sql 2>&1";
			exec($sql_cmd,$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to restore asteriskcdr.sql'));
			exec("/bin/rm -rf /tmp/ampbackups.$fileholder 2>&1",$out_arr,$ret);
			backup_errors($error_cause, $ret, _('failed to remove exploded backup sets from tmp'));
			if(!count($error_cause)){$message=_("Restored CDR logs");}
		break;
	}
	return (count($error_cause))?$error_cause:$message;
}

?>