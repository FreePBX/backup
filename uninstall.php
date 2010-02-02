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
global $asterisk_conf;
$sql = "DELETE FROM backup";
$result = $db->query($sql);
if(DB::IsError($result)) {
	die_freepbx($result->getMessage());
}
//$Cron_Script=$asterisk_conf['astvarlibdir']."/bin/retrieve_backup_cron.php";
//exec($Cron_Script);
backup_retrieve_backup_cron();
sql('DROP TABLE backup');

?>
