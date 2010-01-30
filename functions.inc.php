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
// this function is in charge of looking into the database and creating crontab jobs for each of the Backup Sets
// The crontab file is for user asterisk.
//
// The program preserves any other cron jobs (Not part of the backup) that are installed for the user asterisk 
//

function backup_retrieve_backup_cron(){
	global $amp_conf,$db;
	$table_name = "backup";

	$sql = "SELECT command, id from $table_name WHERE method NOT LIKE 'now%'";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);

	if(empty($results)){
		// grab any other cronjobs that are running as asterisk and NOT associated with backups
		// and issue the schedule to the cron scheduler
		exec("/usr/bin/crontab -l | grep -v ^#\ | grep -v ampbackup.pl",$cron_out,$ret1);
		$cron_out_string = implode("\n",$cron_out);
		exec("/bin/echo '$cron_out_string' | /usr/bin/crontab -",$out_arr,$ret2);
		return ($ret1 == 0 && $ret2 == 0);
	}

	$backup_string = "";
	foreach($results as $result){$backup_string.=$result['command'].' '.$result['id']."\n";}

	// grab any other cronjobs that are running as asterisk and NOT associated with backups,
	// combine with above and re-issue the schedule to the cron scheduler
	exec("/usr/bin/crontab -l | grep -v '^# DO NOT' | grep -v ^'# ('  |  grep -v ampbackup.php",$cron_out,$ret1);
	$cron_out_string = implode("\n",$cron_out);
	$backup_string .= $cron_out_string;

	exec("/bin/echo '$backup_string' | /usr/bin/crontab -",$out_arr,$ret2);

	return ($ret1 == 0 && $ret2 == 0);
}
?>
