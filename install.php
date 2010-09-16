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

global $db;
global $amp_conf;

if (! function_exists("out")) {
	function out($text) {
		echo $text."<br />";
	}
}

if (! function_exists("outn")) {
	function outn($text) {
		echo $text;
	}
}

$autoincrement = (($amp_conf["AMPDBENGINE"] == "sqlite") || ($amp_conf["AMPDBENGINE"] == "sqlite3")) ? "AUTOINCREMENT":"AUTO_INCREMENT";
if($amp_conf["AMPDBENGINE"] == "sqlite3")  {
	$sql="
	CREATE TABLE IF NOT EXISTS backup (
		name varchar(50) default NULL,
		voicemail  varchar(50) default NULL,
		recordings varchar(50) default NULL,
		configurations  varchar(50) default NULL,
		cdr varchar(55) default NULL,
		fop varchar(50)  default NULL,
		minutes varchar(50) default NULL,
		hours varchar(50) default NULL, 
		days varchar(50) default NULL,
		months varchar(50) default NULL,
		weekdays  varchar(50) default NULL,
		command varchar(200) default NULL,
		method varchar(50)  default NULL,
		id int(11) INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
		ftpuser varchar(50),
		ftppass varchar(50),
		ftphost varchar(50),
		ftpdir varchar(150),
		sshuser varchar(50),
		sshkey varchar(150),
		sshhost varchar(50),
		sshdir varchar(150),
		emailaddr varchar(75),
		emailmaxsize varchar(25),
		emailmaxtype varchar(5),
		admin varchar(10),
		overwritebackup varchar(5) default NULL,
		include blob,
		exclude blob,
		sudo varchar(25),
		remotesshhost varchar(50) default NULL, 
		remotesshuser varchar(50) default NULL, 
		remotesshkey varchar(150) default NULL, 
		remoterestore varchar(5) default NULL
	);
	";
}else{
	$sql="
	CREATE TABLE IF NOT EXISTS backup( 
		name varchar(50) default NULL,
		voicemail  varchar(50) default NULL,
		recordings varchar(50) default NULL,
		configurations  varchar(50) default NULL,
		cdr varchar(55) default NULL,
		fop varchar(50)  default NULL,
		minutes varchar(50) default NULL,
		hours varchar(50) default NULL, 
		days varchar(50) default NULL,
		months varchar(50) default NULL,
		weekdays  varchar(50) default NULL,
		command varchar(200) default NULL,
		method varchar(50)  default NULL,
		id int(11) NOT NULL AUTO_INCREMENT,
		ftpuser varchar(50),
		ftppass varchar(50),
		ftphost varchar(50),
		ftpdir varchar(150),
		sshuser varchar(50),
		sshkey varchar(150),
		sshhost varchar(50),
		sshdir varchar(150),
		emailaddr varchar(75),
		emailmaxsize varchar(25),
		emailmaxtype varchar(5),
		admin varchar(10),
		overwritebackup varchar(5) default NULL,
		include blob,
		exclude blob,
		sudo varchar(25),
		remotesshhost varchar(50) default NULL,
		remotesshuser varchar(50) default NULL,
		remotesshkey varchar(150) default NULL,
		remoterestore varchar(5) default NULL,
	PRIMARY KEY (id) );
	";
}
$check = $db->query($sql);
if(DB::IsError($check)) {
	die_freepbx(_("Can not create backup table"));
}

$migrate=$db->getAll('show tables like "Backup"');
if(DB::IsError($check)) {
	die_freepbx(_("Can't check for Backup table")."\n".$result->getMessage());
}
if(count($migrate) > 0){//migrate to new backup structure
	$sql=$db->query('insert into backup (name, voicemail, recordings, configurations, cdr, fop, minutes, hours, days, months, weekdays, command, method, id) select * from Backup;');
	if(DB::IsError($sql)) {
    out(_('ERROR: failed to migrate from old "Backup" table to new "backup" table'));
    out(_('This error can result from a previous incomplete/failed install of'));
    out(_('this module. You should probably uninstall and reinstall this module'));
    out(_('doing so will result in a loss of all your backup settings though previous'));
    out(_('backup data will be preserved.'));
		out(_("Failure Message:")."\n".$sql->getMessage());
	} else {
	  //get data from amportal and populate the table with it
	  //ftp
	  if(isset($amp_conf['FTPBACKUP']) && $amp_conf['FTPBACKUP']==strtolower('yes')){
		  $data['ftpuser']=$amp_conf['FTPUSER'];
		  $data['ftppass']=$amp_conf['FTPPASSWORD'];
		  $data['ftphost']=$amp_conf['FTPSERVER'];
		  $data['ftpdir']=$amp_conf['FTPSUBDIR'];
	  }
	  //ssh
	  if(isset($amp_conf['SSHBACKUP']) && $amp_conf['SSHBACKUP']==strtolower('yes')){
		  $data['sshuser']=$amp_conf['SSHUSER'];
		  $data['sshkey']=$amp_conf['SSHRSAKEY'];
		  $data['sshhost']=$amp_conf['SSHSERVER'];
		  $data['sshdir']=$amp_conf['SSHSUBDIR'];
	  }
	  //includes & excludes
	  if(isset($amp_conf['AMPPROVROOT']) && $amp_conf['AMPPROVROOT']!=''){
		  $data['include']=str_replace(' ',"\n",$amp_conf['AMPPROVROOT']);
		  if(isset($amp_conf['AMPPROVEXCLUDELIST']) && $amp_conf['AMPPROVEXCLUDELIST']!=''){
			  $data['exclude']=str_replace('',"\r",trim($opts['AMPPROVEXCLUDELIST']));
		  }
		  if(isset($amp_conf['AMPPROVEXCLUDE']) && $amp_conf['AMPPROVEXCLUDE']!=''){
			  @$data['exclude']=implode("\r",file($amp_conf['AMPPROVEXCLUDE']));
		  }
	  }
	  if(isset($data)){
		  $db_parms=$data;
		  $data='';
		  //dont include empty values in the query
		  foreach(array_keys($db_parms) as $key){
			  if($db_parms[$key]!=''){
				  $data.=$key.'="'.$db->escapeSimple($db_parms[$key]).'",';
			  }
		  }
	    $data=substr($data,0,-1);//remove trailing ,
		  $sql='UPDATE backup set '.$data;
		  $check = $db->query($sql);
		  if(DB::IsError($check)) {
			  die_freepbx(_('Can not migrate Backup table'));
		  }
		
		  out(_('Backup migration completed'));
	  }else{
		  out(_('Nothing to migrate'));
	  }
	  $sql='DROP TABLE Backup';
	  $check = $db->query($sql);
	  if(DB::IsError($check)) {
		  out(_('ERROR: Failed to remove old "Backup" table. You should uninstall'));
		  out(_('and then re-install this module. Settings will be lost but old'));
		  out(_('backup data will be retained.'));
	  }else{
		  out(_('Old Backup table removed'));
	  }
  }
}

// This should only be needed once. But the original migration did not do it and there is no harm in cleansing the database anyhow
outn(_('Replacing ampbackup.pl in db..'));
$sql=$db->query("UPDATE backup SET command = REPLACE(command,'ampbackup.pl','ampbackup.php')");
if(DB::IsError($sql)) {
  out(_('an error has occured, update not done'));
  out($sql->getMessage());
} else {
  out(_('ok'));
}

// Remove retrieve_backup_cron_from_mysql.pl if still there and a link
//
if (is_link($amp_conf['AMPBIN'].'/retrieve_backup_cron_from_mysql.pl') && readlink($amp_conf['AMPBIN'].'/retrieve_backup_cron_from_mysql.pl') == $amp_conf['AMPWEBROOT'].'/admin/modules/backup/bin/retrieve_backup_cron_from_mysql.pl') {
	outn(_("removing retrieve_backup_cron_from_mysql.pl.."));
	if (unlink($amp_conf['AMPBIN'].'/retrieve_backup_cron_from_mysql.pl')) {
		out(_("removed"));
	} else {
		out(_("failed"));
	}
}

backup_install_retrieve_backup_cron();

function backup_install_retrieve_backup_cron(){
	global $amp_conf,$db;

	$sql = "SELECT command, id from backup WHERE method NOT LIKE 'now%'";
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

//check remote backup fields
$sql='describe backup';
$fields=$db->getAssoc($sql);
if(!array_key_exists('remotesshhost',$fields)){
	out(_('Migrating backup table...'));
	$sql='alter table backup add remotesshhost varchar(50) default NULL,
				add remotesshuser varchar(50) default NULL,
				add remotesshkey varchar(150) default NULL,
				add remoterestore varchar(5) default NULL';
	$q=$db->query($sql);
	if(DB::IsError($q)){
    out(_('WARNING: backup table not migrated'));
  } else {
    out(_('Successfully migrated backup table!'));
  }
}

//check for overwritebackup filed
$sql='describe backup';
$fields=$db->getAssoc($sql);
if(!array_key_exists('overwritebackup',$fields)){
	out(_('Migrating backup table...'));
	$sql='alter table backup add overwritebackup varchar(5) default NULL';
	$q=$db->query($sql);
	if(DB::IsError($q)){
    out(_('WARNING: backup table not migrated'));
  } else {
    out(_('Successfully migrated backup table!'));
  }
}

if (!is_dir($amp_conf['ASTVARLIBDIR'].'/backups')) {
  outn(_('Creating backups directory..'));
  if (mkdir($amp_conf['ASTVARLIBDIR'].'/backups')) {
    out(_('ok'));
  } else {
    out(_('failed'));
    out(sprintf(_('WARNING: failed to create backup directory: %s'),$amp_conf['ASTVARLIBDIR'].'/backups'));
  }
}
?>
