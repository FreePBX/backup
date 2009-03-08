<?php
//This file is part of FreePBX.
//
//    FreePBX is free software: you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation, either version 2 of the License, or
//    (at your option) any later version.
//
//    FreePBX is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with FreePBX.  If not, see <http://www.gnu.org/licenses/>.
//
// Copyright (C) 2005 VerCom Systems, Inc. & Ron Hartmann (rhartmann@vercomsystems.com)
// Asterisk Management Portal Copyright (C) 2004 Coalescent Systems Inc. (info@coalescentsystems.ca)
//

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
