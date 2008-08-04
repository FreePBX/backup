<?php

global $db;
global $asterisk_conf;
$sql = "DELETE FROM Backup";
$result = $db->query($sql);
if(DB::IsError($result)) {
	die_freepbx($result->getMessage());
}
$Cron_Script=$asterisk_conf['astvarlibdir']."/bin/retrieve_backup_cron.php";
exec($Cron_Script);
sql('DROP TABLE Backup');

?>
