<?php

global $db;
global $amp_conf;

$autoincrement = (($amp_conf["AMPDBENGINE"] == "sqlite") || ($amp_conf["AMPDBENGINE"] == "sqlite3")) ? "AUTOINCREMENT":"AUTO_INCREMENT";

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
	  ID int(11) NOT NULL $autoincrement,
	  PRIMARY KEY  (ID)
);";

$check = $db->query($sql);
if(DB::IsError($check)) {
	die("Can not create Backup table");
}

?>
