<?php
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// this function is in charge of looking into the database and creating crontab jobs for each of the Backup Sets
// The crontab file is for user asterisk.
//
// The program preserves any other cron jobs (Not part of the backup) that are installed for the user asterisk 
//

function backup_retrieve_backup_cron() {
	global $amp_conf;
	global $db;

	$table_name = "Backup";

	$sql = "SELECT Command, ID from $table_name WHERE Method NOT LIKE 'now%'";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);

	if(empty($results))  {
		// grab any other cronjobs that are running as asterisk and NOT associated with backups
		// and issue the schedule to the cron scheduler
		//
		exec("/usr/bin/crontab -l | grep -v ^#\ | grep -v ampbackup.pl",$cron_out,$ret1);
		$cron_out_string = implode("\n",$cron_out);
		exec("/bin/echo '$cron_out_string' | /usr/bin/crontab -",$out_arr,$ret2);
		return ($ret1 == 0 && $ret2 == 0);
	}

	$backup_string = "";
	foreach ($results as $result) {
		$backup_string .= $result['Command']." ".$result['ID']."\n";
	}

	// grab any other cronjobs that are running as asterisk and NOT associated with backups,
	// combine with above and re-issue the schedule to the cron scheduler
	//
	exec("/usr/bin/crontab -l | grep -v '^# DO NOT' | grep -v ^'# ('  |  grep -v ampbackup.pl",$cron_out,$ret1);
	$cron_out_string = implode("\n",$cron_out);
	$backup_string .= $cron_out_string;

	exec("/bin/echo '$backup_string' | /usr/bin/crontab -",$out_arr,$ret2);

	return ($ret1 == 0 && $ret2 == 0);
}
?>
