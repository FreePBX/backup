<?php

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
	$sql = "
	CREATE TABLE IF NOT EXISTS Backup (
		  Name varchar(50) default NULL,
		  Voicemail varchar(50) default NULL,
		  Recordings varchar(50) default NULL,
		  Configurations varchar(50) default NULL,
		  CDR varchar(55) default NULL,
		  FOP varchar(50) default NULL,
		  Minutes varchar(50) default NULL,
		  Hours varchar(50) default NULL,
		  Days varchar(50) default NULL,
		  Months varchar(50) default NULL,
		  Weekdays varchar(50) default NULL,
		  Command varchar(200) default NULL,
		  Method varchar(50) default NULL,
		  ID INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT
	);
	";
}
else  {
	$sql = "
	CREATE TABLE IF NOT EXISTS Backup (
		  Name varchar(50) default NULL,
		  Voicemail varchar(50) default NULL,
		  Recordings varchar(50) default NULL,
		  Configurations varchar(50) default NULL,
		  CDR varchar(55) default NULL,
		  FOP varchar(50) default NULL,
		  Minutes varchar(50) default NULL,
		  Hours varchar(50) default NULL,
		  Days varchar(50) default NULL,
		  Months varchar(50) default NULL,
		  Weekdays varchar(50) default NULL,
		  Command varchar(200) default NULL,
		  Method varchar(50) default NULL,
		  ID int(11) NOT NULL AUTO_INCREMENT,
		  PRIMARY KEY  (ID)
	);
	";
}
$check = $db->query($sql);
if(DB::IsError($check)) {
	die_freepbx("Can not create Backup table");
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

?>
