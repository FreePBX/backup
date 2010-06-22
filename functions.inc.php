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
 *     along with FreePBX.  If not, see <http: * www.gnu.org/licenses/>.
 * 
 * 
 *  this function is in charge of looking into the database and creating crontab jobs for each of the Backup Sets
 *  The crontab file is for user asterisk.
 * 
 *  The program preserves any other cron jobs (Not part of the backup) that are installed for the user asterisk 
 */ 

function backup_retrieve_backup_cron(){
	global $amp_conf,$db;
	$table_name = "backup";

	$sql = "SELECT command, id from $table_name WHERE method NOT LIKE 'now%'";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);

	if(empty($results)){
		// grab any other cronjobs that are running as asterisk and NOT associated with backups
		// and issue the schedule to the cron scheduler
		exec("/usr/bin/crontab -l|grep -v '^# DO NOT'|grep -v ^'# ('|grep -v ampbackup.pl|grep -v ampbackup.php",$cron_out,$ret1);
		$cron_out_string = implode("\n",$cron_out);
		exec("/bin/echo '$cron_out_string' | /usr/bin/crontab -",$out_arr,$ret2);
		return ($ret1 == 0 && $ret2 == 0);
	}

	$backup_string = "";
	foreach($results as $result){$backup_string.=$result['command'].' '.$result['id']."\n";}

	// grab any other cronjobs that are running as asterisk and NOT associated with backups,
	// combine with above and re-issue the schedule to the cron scheduler
	exec("/usr/bin/crontab -l|grep -v '^# DO NOT'|grep -v ^'# ('|grep -v ampbackup.pl|grep -v ampbackup.php",$cron_out,$ret1);
	$cron_out_string = implode("\n",$cron_out);
	$backup_string .= $cron_out_string;

	exec("/bin/echo '$backup_string' | /usr/bin/crontab -",$out_arr,$ret2);

	return ($ret1 == 0 && $ret2 == 0);
}

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
			$html.="<li><a class=\"info\" href=\"javascript:decision('"._("Are you sure you want to Restore this file set?\\nDoing so will permanently delete any new voicemail you have in your mailbox\\nsince this backup on")." $file!','config.php?type=$type&display=$display&action=restored&dir=$dir&filetype=VoiceMail&file=$file')\">";
			$html.=_('Restore VoiceMail Files').'<span>'; 
			$html.=_('Restore your Voicemail files from this backup set.  NOTE! This will delete any voicemail currently in the voicemail boxes.');
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
	global $asterisk_conf,$amp_conf;
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
      backup_errors($error_cause, $ret, _('failed to remove voicemail directory'));
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
			if(!count($error_cause)){$message=_("Restored VoiceMail");}
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

function backup_get(){
  global $db;
  $sql = "SELECT * FROM backup ORDER BY name ASC";
  $results = $db->getAll($sql);
  if(DB::IsError($results)) {
    $results = null;
  }
  return $results;
}

function backup_delete_set($id=''){
  global $db,$asterisk_conf,$amp_conf;
	$sql = "DELETE FROM backup WHERE id =?";
  $result = $db->query($sql,array($id));
  if(DB::IsError($result)) {
    die_freepbx($result->getMessage());
  }
	backup_retrieve_backup_cron();
}

function backup_save_schedule($parms){
	global $db,$asterisk_conf,$amp_conf;
	//build query based on the following keys
	$parms_num=array('admin','cdr','command','configurations','days','emailaddr',
									'emailmaxsize','emailmaxtype','exclude','fop','ftpdir','ftphost',
									'ftppass','ftpuser','hours','id','include','method','minutes',
									'months','name','recordings','sshdir','sshhost','sshkey','sshuser',
									'remotesshhost','remotesshuser','remotesshkey','remoterestore',
									'sudo','voicemail','weekdays','overwritebackup');
	foreach($parms_num as $dprm){$db_parms[$dprm]=isset($parms[$dprm])?$parms[$dprm]:'';}
	$keys=$vals='';
	//dont include empty values in the query
	foreach(array_keys($db_parms) as $key){
		if($db_parms[$key]!=''){
			$keys.=$key.',';
		}
	}
  foreach(array_values($db_parms) as $val){
		if($val!=''){
			$vals.='"'.$db->escapeSimple($val).'",';
		}
	}
  $keys=substr($keys,0,-1);$vals=substr($vals,0,-1);
	$sql='INSERT INTO backup ('.$keys.') VALUES ('.$vals.')';
	$result = $db->query($sql);
  if(DB::IsError($result)) {
    die_freepbx($result->getMessage().'<hr>'.$sql);
  }
  
	if($parms['method']=='now' && $result){
		$latest=$db->getOne('select last_insert_id()');
		$backup_script=$asterisk_conf['astvarlibdir'].'/bin/ampbackup.php '.$latest;
		exec($backup_script,$res);
	}
	backup_retrieve_backup_cron();
	
}
function backup_get_string($parms){
	global $asterisk_conf;
	switch($parms['backup_schedule']){
		case 'hourly':
			$cron_string='0 * * * * ';
		break;
		case 'daily':
			$cron_string='0 0 * * * ';
		break;
		case 'weekly':
			$cron_string='0 0 * * 0 ';
		break;
		case 'monthly':
			$cron_string='0 0 1 * * ';
		break;
		case 'yearly':
			$cron_string='0 0 1 1 * ';
		break;
		case 'now':
			$cron_string='0 0 0 0 0 ';
		break;
		case 'follow_schedule':
			$mins_string=$hours_string=$days_string=$months_string=$weekdays_string='';
			if(is_array($parms['mins'])){
				foreach($parms['mins'] as $value){
	        $mins_string.=":$value:";
	      }
	    }else{
	    	$mins_string=":0:";
	    }
			if(is_array($parms['hours'])){
				foreach($parms['hours'] as $value){
			    $hours_string.=":$value:";
			  }
			}else{
				$hours_string=":0:";
			}
			if(isset($parms['all_days']) && $parms['all_days']=='0'){
				foreach($parms['days'] as $value){
			   	$days_string.=":$value:";
			  }
			}else{
				$days_string="*";
			}
			if(isset($parms['all_months']) && $parms['all_months']=='0'){
				foreach($parms['months'] as $value){
			    $months_string.=":$value:";
			  }
			}else{
				$months_string="*";
			}
			if(isset($parms['all_weekdays']) && $parms['all_weekdays']=='0'){
				foreach($parms['weekdays'] as $value){
			   $weekdays_string.=":$value:";
			  }
			}else{
				$weekdays_string="*";
			}
		  $cron_mins_string=trim($mins_string,":");
			$cron_hours_string=trim($hours_string,":");
			$cron_days_string=trim($days_string,":");
			$cron_months_string=trim($months_string,":");
			$cron_weekdays_string=trim($weekdays_string,":");
			$cron_string=str_replace("::", ",", "$cron_mins_string $cron_hours_string $cron_days_string $cron_months_string $cron_weekdays_string");
		break;
	}
	$backup_string['name']=$parms['name'];
	$backup_string['method']=$parms['backup_schedule'];
	$backup_string['minutes']=isset($mins_string)?$mins_string:'';
	$backup_string['hours']=isset($hours_string)?$hours_string:'';
	$backup_string['days']=isset($days_string)?$days_string:'';
	$backup_string['months']=isset($months_string)?$months_string:'';
	$backup_string['weekdays']=isset($weekdays_string)?$weekdays_string:'';
	$backup_string['command']=$cron_string.' '.$asterisk_conf['astvarlibdir'].'/bin/ampbackup.php';
	$ret=array_merge($parms,$backup_string);
	return ($ret);
}
function Get_Backup_Times($id){
  global $db;
  $sql = "SELECT minutes, hours, days, months, weekdays, method From backup where id=?";
  $results = $db->getAll($sql,array($id));
  if(DB::IsError($results)) {
    $results = Array(null, null, null, null, null, null);
  }
  return $results;
}
function Get_Backup_Options($id){
  global $db;
  $sql = "SELECT * FROM backup WHERE id=?";
  $results = $db->getAll($sql,array($id),DB_FETCHMODE_ASSOC);
  if(DB::IsError($results)) {
    $results = Array(null, null, null, null, null, null);
  }
  return $results[0];
}
function backup_showopts($id=''){
	global $amp_conf;
	$tabindex=0;
	if ($id==''){
		$opts=array('name','voicemail','recordings','configurations','cdr','fop');
		foreach($opts as $o){$opts[$o]='';}//set defaults to ''
	}else{
		$opts=Get_Backup_Options($id);
	}
	$files=($opts['include']||$opts['exclude']);
	$ftp=($opts['ftpuser']||$opts['ftppass']||$opts['ftphost']||$opts['ftpdir']);
	$ssh=($opts['sshuser']||$opts['sshkey']||$opts['sshhost']||$opts['sshdir']);
	$email=($opts['emailaddr']);
	$remote=($opts['remotesshhost']||$opts['remotesshuser']||$opts['remotesshkey']||$opts['remoterestore']);
	$advanced=($amp_conf['AMPBACKUPADVANCED']||$opts['sudo']);
	?>
	<style type="text/css">
		tr .tog{cursor:pointer;}
		tr .tog span{color:black}
		<?php
			$sections=array('files','ftp','ssh','email','remote','advanced');
			foreach($sections as $sec){
				if(!$$sec){echo '.hide.'.$sec.'{display:none}'."\n";}
			}
			if(!$advanced){echo '.advanced{display:none}'."\n";}
		?>
		h5{margin-top:10px;margin-bottom:10px}
	</style>
	<script language="javascript" type="text/javascript">
		$(document).ready(function() {
   		$('.tog').click(function(){
   			var tclass = $(this).attr('class'); tclass = tclass.replace('tog ', '');
   			var togspan = $(this).find('span');
   			if(togspan.text()=='+'){
   				togspan.text('- ');
		 			$('.'+tclass).show();
		 		}else{
		 			togspan.text('+');
		 			$('.'+tclass).not('.tog').hide();
		 		}
			 });
			 $('.sysconfigdep').click(function(){
			 		if($(this).is(':checked')){
			 			$('.sysconfig').attr('checked', true);
			 		}
			 })
			 $('.sysconfig').click(function(){
			 		if(!$(this).is(':checked')){
			 			$('.sysconfigdep').attr('checked', false);
			 		}
			 })
		});
	</script>
	<table>
	<tr><td colspan="2"><h5><?php echo _("Basic Settings")?><hr></h5></td></tr>
  <tr>
      <td><a href="#" class="info"><?php echo _("Schedule Name:")?><span><?php echo _("Give this Backup Schedule a brief name to help you identify it.");?></span></a></td>
      <td><input type="text" name="name" value="<?php echo (isset($opts['name'])?$opts['name']:''); ?>" tabindex="<?php echo ++$tabindex;?>"></td>
  </tr>
	<tr>
 		<td><a href="#" class="info"><?php echo _("Admin Web Directory");?><span><?php echo _("Backup the admin web directory (i.e. the static FreePBX web files). This is useful to have during a restore to prevent a version mismatch, but can dramatically increase the backup size.");?></span></a>: </td>
 		<td><input type="checkbox" name="admin" value="yes" class="sysconfigdep" <?php echo ($opts['admin']=='yes')?'checked':''; ?>/></td>
 	</tr>
 	<tr>
 		<td><a href="#" class="info"><?php echo _("CDR");?><span><?php echo _("Backup the System Call Detail Reporting (HTML and Database)");?></span></a>: </td>
 		<td><input type="checkbox" name="cdr" value="yes" <?php echo ($opts['cdr']=='yes')?'checked':''; ?>/></td>
 	</tr>
	<tr>
 		<td><a href="#" class="info"><?php echo _("Operator Panel");?><span><?php echo _("Backup the Operator Panel (HTML and Database)");?></span></a>: </td>
 		<td><input type="checkbox" name="fop" value="yes" <?php echo ($opts['fop']=='yes')?'checked':''; ?>/></td>
	</tr>
	<tr>
 		<td><a href="#" class="info"><?php echo _("Overwrite Backup Settings");?><span><?php echo _("When restoring the backup, if this option is selected, all saved backups and their schedules will be overwriten. Leaving this unchecked will restore all other configurations EXCEPT for those related to backup. When doing a remote backup and restore, this option is always forced to no.");?></span></a>: </td>
 		<td><input type="checkbox" name="overwritebackup" value="yes" class="sysconfigdep" <?php echo ($opts['overwritebackup']=='yes')?'checked':''; ?>/></td>
 	</tr>	
	<tr>
 		<td><a href="#" class="info"><?php echo _("System Recordings");?><span><?php echo _("Backup the System Recordings (AutoAttendant, Music On Hold, System Recordings)");?></span></a>: </td>
 		<td><input type="checkbox" name="recordings" value="yes" <?php echo ($opts['recordings']=='yes')?'checked':''; ?> /></td>
 	</tr>
	<tr>
 		<td><a href="#" class="info"><?php echo _("VoiceMail");?><span><?php echo _("Backup the System VoiceMail Boxes... CAUTION: Could result in large file");?></span></a>: </td>
 		<td><input type="checkbox" name="voicemail"  tabindex="<?php echo ++$tabindex;?>" value="yes" <?php echo ($opts['voicemail']=='yes')?'checked':''; ?> /></td>
 	</tr>
 	<tr>
 		<td><a href="#" class="info"><?php echo _("System Configuration");?><span><?php echo _("Backup the System Configurations (Database, etc files, SQL Database, astdb)");?></span></a>: </td>
 		<td><input type="checkbox" name="configurations" value="yes" class="sysconfig" <?php echo ($opts['configurations']=='yes')?'checked':''; ?>/></td>
 	</tr>

	<tr><td colspan="2" class="tog files"><h5><span><?php echo $files?'-':'+';?></span><?php echo _(' Additional Files')?><hr></h5></td></tr>
	<tr class="hide files">
 		<td><a href="#" class="info"><?php echo _("Additional files and folders");?><span><?php echo _("Backup any additional files and folders listed here.");?></span></a>: </td>
 		<td><textarea name="include" style="width: 400px" /><?php echo $opts['include']; ?></textarea></td>
	</tr>
	<tr class="hide files">
 		<td><a href="#" class="info"><?php echo _("Exclude files and folders");?><span><?php echo _("Exclude any files and folders from the include listed above.");?></span></a>: </td>
 		<td><textarea name="exclude" style="width: 400px" /><?php echo $opts['exclude']; ?></textarea></td>
	</tr>
	
	<tr><td colspan="2" class="tog ftp"><h5><span><?php echo $ftp?'-':'+';?></span><?php echo _(' FTP Settings')?><hr></h5></td></tr>
	<tr class="hide ftp">
 		<td><a href="#" class="info"><?php echo _("FTP User Name");?><span><?php echo _('Enter your FTP user name');?></span></a>: </td>
 		<td><input type="text" name="ftpuser" value="<?php echo $opts['ftpuser']; ?>" /></td>
	</tr>
	<tr class="hide ftp">
 		<td><a href="#" class="info"><?php echo _("FTP Password");?><span><?php echo _('Enter your FTP password');?></span></a>: </td>
 		<td><input type="text" name="ftppass" value="<?php echo $opts['ftppass']; ?>" /></td>
	</tr>
	<tr class="hide ftp">
 		<td><a href="#" class="info"><?php echo _("FTP Hostname");?><span><?php echo _('IP address or FQDN of FTP server');?></span></a>: </td>
 		<td><input type="text" name="ftphost" value="<?php echo $opts['ftphost']; ?>" /></td>
	</tr>
	<tr class="hide ftp">
 		<td><a href="#" class="info"><?php echo _("FTP Directory");?><span><?php echo _('Directory on FTP server where the backup should be copied to');?></span></a>: </td>
 		<td><input type="text" name="ftpdir" value="<?php echo $opts['ftpdir']; ?>" /></td>
	</tr>
	
	<tr><td colspan="2" class="tog ssh"><h5><span><?php echo $ssh?'-':'+';?></span><?php echo _(' SSH Settings')?><hr></h5></td></tr>
	<tr class="hide ssh">
 		<td><a href="#" class="info"><?php echo _("SSH User Name");?><span><?php echo _('Enter your SSH user name');?></span></a>: </td>
 		<td><input type="text" name="sshuser" value="<?php echo $opts['sshuser']; ?>" /></td>
	</tr>
	<tr class="hide ssh">
 		<td><a href="#" class="info"><?php echo _("SSH Key");?><span><?php echo _('Location of ssh private key to be used when connect to a host');?></span></a>: </td>
 		<td><input type="text" name="sshkey" value="<?php echo $opts['sshkey']; ?>" /></td>
	</tr>
	<tr class="hide ssh">
 		<td><a href="#" class="info"><?php echo _("SSH Hostname");?><span><?php echo _('IP address or FQDN of remote ssh host');?></span></a>: </td>
 		<td><input type="text" name="sshhost" value="<?php echo $opts['sshhost']; ?>" /></td>
	</tr>
	<tr class="hide ssh">
 		<td><a href="#" class="info"><?php echo _("SSH Directory");?><span><?php echo _('Directory on remote server where the backup should be copied to');?></span></a>: </td>
 		<td><input type="text" name="sshdir" value="<?php echo $opts['sshdir']; ?>" /></td>
	</tr>	
	
	<tr><td colspan="2" class="tog email"><h5><span><?php echo $email?'-':'+';?></span><?php echo _(' Email Settings')?><hr></h5></td></tr>
	<tr class="hide email">
 		<td><a href="#" class="info"><?php echo _("Email Address");?><span><?php echo _('Email address where backups should be emailed to');?></span></a>: </td>
 		<td><input type="text" name="emailaddr" value="<?php echo $opts['emailaddr']; ?>" /></td>
	</tr>	
	<tr class="hide email">
 		<td><a href="#" class="info"><?php echo _("Max email size");?><span><?php echo _('The maximum size a backup can be and still be emailed. Some email servers limit the size of email attachments, this will make sure that files larger than the max size are not sent. Valid options include: xB, xKB, xMB');?></span></a>: </td>
 		<td><select name="emailmaxsize">
 				<?php if($opts['emailmaxsize']==''){$opts['emailmaxsize']=25;}//default max email size to 25
				 for($i=1;$i<21;$i++){	  echo '<option value="'.$i.'" '.($opts['emailmaxsize']==$i?'selected':'').' >'.$i.'</option>';}
 				 for($i=25;$i<51;$i+=5){	echo '<option value="'.$i.'" '.($opts['emailmaxsize']==$i?'selected':'').' >'.$i.'</option>';}
 				 for($i=60;$i<101;$i+=10){echo '<option value="'.$i.'" '.($opts['emailmaxsize']==$i?'selected':'').' >'.$i.'</option>';}?>
				</select>
				<select name="emailmaxtype">
 				<?php if($opts['emailmaxtype']==''){$opts['emailmaxtype']='MB';}//default max type to MB
				 $maxtypes=array('B','KB','MB');
 				foreach($maxtypes as $max){echo '<option value="'.$max.'" '.($opts['emailmaxtype']==$max?'selected':'').' >'.$max.'</option>';	}	?>
			</select>
 			</td>
	</tr>
	
	<tr><td colspan="2" class="tog remote"><h5><span><?php echo $remote?'-':'+';?></span><?php echo _(' Remote Backup Options')?><hr></h5></td></tr>
	<tr class="hide remote">
		<td><a href="#" class="info"><?php echo _('Remote SSH Hostname');?><span><?php echo _('Run this backup on a remote server. The backup will then be copied over to this server');?></span></a>: </td>
 		<td><input type="input" name="remotesshhost"  tabindex="<?php echo ++$tabindex;?>" value="<?php echo $opts['remotesshhost']; ?>"/></td>
	</tr>
	<tr class="hide remote">
		<td><a href="#" class="info"><?php echo _('Remote SSH User');?><span><?php echo _('Username to use when connecting to remote server. Defaults to the user apache is running as on this system.');?></span></a>: </td>
 		<td><input type="input" name="remotesshuser"  tabindex="<?php echo ++$tabindex;?>" value="<?php echo $opts['remotesshuser']; ?>"/></td>
	</tr>
	<tr class="hide remote">
 		<td><a href="#" class="info"><?php echo _('Remote SSH Key');?><span><?php echo _('Location of ssh private key to be used when connecting to a host');?></span></a>: </td>
 		<td><input type="text" name="remotesshkey" tabindex="<?php echo ++$tabindex;?>" value="<?php echo $opts['remotesshkey']; ?>" /></td>
	</tr>
	<tr class="hide remote">
 		<td><a href="#" class="info"><?php echo _('Restore to this server');?><span><?php echo _('Restore the backup to this server. Use this option to create a delayed time backup of another server on this one.');?></span></a>: </td>
 		<td><input type="checkbox" name="remoterestore" tabindex="<?php echo ++$tabindex;?>" value="yes" <?php echo ($opts['remoterestore']=='yes')?'checked':''; ?> /></td>
	</tr>
	<tr class="advanced"><td colspan="2" class="tog advanced" ><h5><span><?php echo $advanced?'-':'+';?></span><?php echo _(' Advanced Options')?><hr></h5></td></tr>
	<tr  class="hide advanced">
		<td><a href="#" class="info"><?php echo _("Sudo");?><span><?php echo _('Use sudo when performing a backup. NOTE: THIS HAS SEVERE SECURITY IMPLICATIONS!');?></span></a>: </td>
 		<td><input type="checkbox" name="sudo"  tabindex="<?php echo ++$tabindex;?>" value="yes" <?php echo ($opts['sudo']=='yes')?'checked':''; ?> /></td>
	</tr>
	<?php
}
function Schedule_Show_Minutes($Minutes_Set=""){
	echo "<br><br><table> <tr>";
	echo "<td valign=top><select multiple size=12 name=mins[]>";
	for ($minutes=0; $minutes<=59; $minutes++){
/* if (($minutes==12)||($minutes==24)||($minutes==36)||($minutes==48)) { echo 
"</select></td>"; echo "<td width=2 valign=top><select multiple size=12 
name=mins[]>"; } */ 
		if (strstr($Minutes_Set,":$minutes:"))
			echo "<option value=\"$minutes\" selected>$minutes";
		else
			echo "<option value=\"$minutes\" >$minutes";
	}
	echo "</select></td>";
	echo "</tr></table></td>";
}
function Schedule_Show_Hours($Hours_Set=""){
	echo "<br><br><table> <tr>";
	echo "<td valign=top><select multiple size=12 name=hours[]>";
	for ($hours=0; $hours<=23; $hours++)
	{
	/* if ($hours==12) { echo "</select></td>"; echo "<td valign=top><select multiple size=12 name=hours[]>"; } */ 
		if (strstr($Hours_Set,":$hours:"))
			echo "<option value=\"$hours\" selected>$hours";
		else
			echo "<option value=\"$hours\" >$hours";
	}
	echo "</select></td>";
	echo "</tr></table></td>";
}

function Schedule_Show_Days($Days_Set=""){
	if (($Days_Set=="") || ($Days_Set=="*")){
	echo "<input type=radio name=all_days value=1 checked>"; echo _("All"); echo "<br>";
	echo "<input type=radio name=all_days value=0 >"; echo _("Selected"); echo "<br>";
	}
	else{
	echo "<input type=radio name=all_days value=1 >"; echo _("All"); echo "<br>";
	echo "<input type=radio name=all_days value=0 checked>"; echo _("Selected"); echo "<br>";
	}

	echo "<table> <tr>";
	echo "<td valign=top><select multiple size=12 name=days[]>";
	for ($days=1; $days<=31; $days++)
	{
/* if (($days==13)||($days==25)) { echo "</select></td>"; echo "<td valign=top><select multiple size=12 name=days[]>"; } */ 
	if ((strstr($Days_Set,":$days:"))|| ($Days_Set=="*"))
			echo "<option value=\"$days\" selected>$days";
		else
			echo "<option value=\"$days\" >$days";
	}
	echo "</select></td>";
	echo "</tr></table></td>";
}

function Schedule_Show_Months($Months_Set=""){
	if (($Months_Set=="") || ($Months_Set=="*")){
	echo "<input type=radio name=all_months value=1 checked>"; echo _("All"); echo "<br>";
	echo "<input type=radio name=all_months value=0 >"; echo _("Selected"); echo "<br>";
	}
	else{
	echo "<input type=radio name=all_months value=1 >"; echo _("All"); echo "<br>";
	echo "<input type=radio name=all_months value=0 checked>"; echo _("Selected"); echo "<br>";
	}
	echo "<table> <tr>";
	echo "<td valign=top><select multiple size=12 name=months[]>";
	echo ((strstr($Months_Set,":1:") || ($Months_Set=="*")) ? '<option value="1" selected>'._("January"):'<option value="1" >'._("January"));
 	echo ((strstr($Months_Set,":2:") || ($Months_Set=="*")) ? '<option value="2" selected>'._("February"):'<option value="2" >'._("February"));
 	echo ((strstr($Months_Set,":3:") || ($Months_Set=="*")) ? '<option value="3" selected>'._("March"):'<option value="3" >'._("March"));
 	echo ((strstr($Months_Set,":4:") || ($Months_Set=="*")) ? '<option value="4" selected>'._("April"):'<option value="4" >'._("April"));
 	echo ((strstr($Months_Set,":5:") || ($Months_Set=="*")) ? '<option value="5" selected>'._("May"):'<option value="5" >'._("May"));
 	echo ((strstr($Months_Set,":6:") || ($Months_Set=="*")) ? '<option value="6" selected>'._("June"):'<option value="6" >'._("June"));
 	echo ((strstr($Months_Set,":7:") || ($Months_Set=="*")) ? '<option value="7" selected>'._("July"):'<option value="7" >'._("July"));
 	echo ((strstr($Months_Set,":8:") || ($Months_Set=="*")) ? '<option value="8" selected>'._("August"):'<option value="8" >'._("August"));
 	echo ((strstr($Months_Set,":9:") || ($Months_Set=="*")) ? '<option value="9" selected>'._("September"):'<option value="9" >'._("September"));
 	echo ((strstr($Months_Set,":10:") || ($Months_Set=="*")) ? '<option value="10" selected>'._("October"):'<option value="10" >'._("October"));
 	echo ((strstr($Months_Set,":11:") || ($Months_Set=="*")) ? '<option value="11" selected>'._("November"):'<option value="11" >'._("November"));
 	echo ((strstr($Months_Set,":12:") || ($Months_Set=="*")) ? '<option value="12" selected>'._("December"):'<option value="12" >'._("December"));

	echo "</select></td>";
	echo "</tr></table></td>";
}

function Schedule_Show_Weekdays($Weekdays_Set=""){
	if (($Weekdays_Set=="") || ($Weekdays_Set=="*")){
	echo "<input type=radio name=all_weekdays value=1 checked>";echo _("All"); echo "<br>";
	echo "<input type=radio name=all_weekdays value=0 >";echo _("Selected"); echo "<br>";
	}
	else{
	echo "<input type=radio name=all_weekdays value=1 >";echo _("All"); echo "<br>";
	echo "<input type=radio name=all_weekdays value=0 checked>";echo _("Selected"); echo "<br>";
	}
	echo "<table> <tr>";
	echo "<td valign=top><select multiple size=12 name=weekdays[]>";
	echo ((strstr($Weekdays_Set,":1:") || ($Weekdays_Set=="*")) ? '<option value="1" selected>'._("Monday"):'<option value="1" >'._("Monday"));
	echo ((strstr($Weekdays_Set,":2:") || ($Weekdays_Set=="*")) ? '<option value="2" selected>'._("Tuesday"):'<option value="2" >'._("Tuesday"));
	echo ((strstr($Weekdays_Set,":3:") || ($Weekdays_Set=="*")) ? '<option value="3" selected>'._("Wednesday"):'<option value="3" >'._("Wednesday"));
	echo ((strstr($Weekdays_Set,":4:") || ($Weekdays_Set=="*")) ? '<option value="4" selected>'._("Thursday"):'<option value="4" >'._("Thursday"));
	echo ((strstr($Weekdays_Set,":5:") || ($Weekdays_Set=="*")) ? '<option value="5" selected>'._("Friday"):'<option value="5" >'._("Friday"));
	echo ((strstr($Weekdays_Set,":6:") || ($Weekdays_Set=="*")) ? '<option value="6" selected>'._("Saturday"):'<option value="6" >'._("Saturday"));
	echo ((strstr($Weekdays_Set,":0:") || ($Weekdays_Set=="*")) ? '<option value="0" selected>'._("Sunday"):'<option value="0" >'._("Sunday"));

	echo "</select></td>";
	echo "</tr></table></td>";
}
function show_quickbar($method=''){
?>
	<tr bgcolor=#b7b7b7> <td colspan=6><?php echo _("Run Backup");?> 
	<select name=backup_schedule>
	<option value=follow_schedule <?php echo ($method=="follow_schedule"?"SELECTED":'')?>><?php echo _("Follow Schedule Below");?>
	<option value=now <?php echo ($method=="now"?"SELECTED":'')?>><?php echo _("Now");?>
	<option value=daily <?php echo ($method=="daily"?"SELECTED":'')?>><?php echo _("Daily (at midnight)");?>
	<option value=weekly <?php echo ($method=="weekly"?"SELECTED":'')?>><?php echo _("Weekly (on Sunday)");?>
	<option value=monthly <?php echo ($method=="monthly"?"SELECTED":'')?>><?php echo _("Monthly (on the 1st)");?>
	<option value=yearly <?php echo ($method=="yearly"?"SELECTED":'')?>><?php echo _("Yearly (on 1st Jan)");?>
	</select>
	</td></tr>
<?php
}
function show_schedule($quickbar="no", $BackupID=""){
	if ($BackupID==""){
		$Minutes="";
		$Hours="";
		$Days="";
		$Months="";
		$Weekdays="";
		$Method="follow_schedule";
	}else{
		$backup_times=Get_Backup_Times($BackupID);
		foreach ($backup_times as $bk_times) 
			$Minutes=$bk_times[0]?"$bk_times[0]":'';
			$Hours=$bk_times[1]?"$bk_times[1]":'';
			$Days=$bk_times[2]?"$bk_times[2]":'';
			$Months=$bk_times[3]?"$bk_times[3]":'';
			$Weekdays=$bk_times[4]?"$bk_times[4]":'';
			$Method=$bk_times[5]?"$bk_times[5]":'';
		
	}
	if ($quickbar=="yes")
		show_quickbar($Method);
	else
		echo "<tr bgcolor=#7f7f7f>";
	echo "<td><b>"._("Minutes")."</b></td> <td><b>"._("Hours")."</b></td> <td><b>"._("Days")."</b></td> <td><b>"._("Months")."</b></td><td><b>"._("Weekdays")."</b></td> </tr> <tr bgcolor=#b7b7b7>";
	echo "<td valign=top>";
	Schedule_Show_Minutes($Minutes); 
	echo "<td valign=top>";
	Schedule_Show_Hours($Hours);
	echo "<td valign=top>";
	Schedule_Show_Days($Days); 
	echo "<td valign=top>";
	Schedule_Show_Months($Months);
	echo "<td valign=top>";
	Schedule_Show_Weekdays($Weekdays);
}


?>
