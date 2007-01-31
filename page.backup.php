<?php 
// backup.php Copyright (C) 2005 VerCom Systems, Inc. & Ron Hartmann (rhartmann@vercomsystems.com)
// Asterisk Management Portal Copyright (C) 2004 Coalescent Systems Inc. (info@coalescentsystems.ca)
//
//This program is free software; you can redistribute it and/or
//modify it under the terms of the GNU General Public License
//as published by the Free Software Foundation; either version 2
//of the License, or (at your option) any later version.
//
//This program is distributed in the hope that it will be useful,
//but WITHOUT ANY WARRANTY; without even the implied warranty of
//MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//GNU General Public License for more details.
?>

<?php
include_once "schedule_functions.php";
global $asterisk_conf;
$action = isset($_REQUEST['action'])?$_REQUEST['action']:'';
$display='backup';
$type = 'tool';

$dir=isset($_REQUEST['dir'])?$_REQUEST['dir']:'';
$extdisplay=isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:'';
$file=isset($_REQUEST['file'])?$_REQUEST['file']:'';
$filetype=isset($_REQUEST['filetype'])?$_REQUEST['filetype']:'';
$ID=isset($_REQUEST['backupid'])?$_REQUEST['backupid']:'';
$name=isset($_REQUEST['name'])?$_REQUEST['name']:'backup';

// Santity check passed params
if (strstr($dir, '..') || strpos($dir, '\'') || strpos($dir, '"') || strpos($dir, '\'') || strpos($dir,'\`') ||
    strstr($file, '..') || strpos($file, '\'') || strpos($file, '"') || strpos($file, '\'') || strpos($file,'\`') ||
    strpos($ID, '.') || strpos($ID, '\'') || strpos($ID, '"') || strpos($ID, '\'') || strpos($ID,'\`') ||
    strpos($filetype, '.') || strpos($filetype, '\'') || strpos($filetype, '"') || strpos($filetype, '\'') || strpos($filetype,'\`')) {
	print "You're trying to use an invalid character. Please don't.\n";
	exit;
}


switch ($action) {
	case "addednew":
		$ALL_days=isset($_POST['all_days'])?$_POST['all_days']:'';
		$ALL_months=isset($_POST['all_months'])?$_POST['all_months']:'';
		$ALL_weekdays=isset($_POST['all_weekdays'])?$_POST['all_weekdays']:'';

		$backup_schedule=isset($_REQUEST['backup_schedule'])?$_REQUEST['backup_schedule']:'';
		$name=(empty($_REQUEST['name'])?'backup':$_REQUEST['name']);
		$mins=isset($_REQUEST['mins'])?$_REQUEST['mins']:'';
		$hours=isset($_REQUEST['hours'])?$_REQUEST['hours']:'';
		$days=isset($_REQUEST['days'])?$_REQUEST['days']:'';
		$months=isset($_REQUEST['months'])?$_REQUEST['months']:'';
		$weekdays=isset($_REQUEST['weekdays'])?$_REQUEST['weekdays']:'';
		
		$backup_options[]=$_REQUEST['bk_voicemail'];
		$backup_options[]=$_REQUEST['bk_sysrecordings'];
		$backup_options[]=$_REQUEST['bk_sysconfig'];
		$backup_options[]=$_REQUEST['bk_cdr'];
		$backup_options[]=$_REQUEST['bk_fop'];
	
		$Backup_Parms=Get_Backup_String($name,$backup_schedule, $ALL_days, $ALL_months, $ALL_weekdays, $mins, $hours, $days, $months, $weekdays);
		Save_Backup_Schedule($Backup_Parms, $backup_options);
	break;
	case "edited":
		Delete_Backup_set($ID);
		$ALL_days=$_REQUEST['all_days'];
		$ALL_months=$_REQUEST['all_months'];
		$ALL_weekdays=$_REQUEST['all_weekdays'];

		$backup_schedule=$_REQUEST['backup_schedule'];
		$mins=$_REQUEST['mins'];
		$hours=$_REQUEST['hours'];
		$days=$_REQUEST['days'];
		$months=$_REQUEST['months'];
		$weekdays=$_REQUEST['weekdays'];
		
		$backup_options[]=$_REQUEST['bk_voicemail'];
		$backup_options[]=$_REQUEST['bk_sysrecordings'];
		$backup_options[]=$_REQUEST['bk_sysconfig'];
		$backup_options[]=$_REQUEST['bk_cdr'];
		$backup_options[]=$_REQUEST['bk_fop'];
	
		$Backup_Parms=Get_Backup_String($name,$backup_schedule, $ALL_days, $ALL_months, $ALL_weekdays, $mins, $hours, $days, $months, $weekdays);
		Save_Backup_Schedule($Backup_Parms, $backup_options);
	break;
	case "delete":
		Delete_Backup_set($ID);
	break;
	case "deletedataset":
		exec("/bin/rm -rf '$dir'");
	break;
	case "deletefileset":
		exec("/bin/rm -rf '$dir'");
	break;
	case "restored":
		$Message=Restore_Tar_Files($dir, $file, $filetype, $display);
		// Regenerate all the ASTDB stuff. Note, we need a way to do speedials and other astdb stuff here.
		needreload();
		redirect_standard();
	break;
}


?>
</div>
<div class="rnav"><ul>
    <li><a href="config.php?type=<?php echo urlencode($type)?>&display=<?php echo urlencode($display)?>&action=add"><?php echo _("Add Backup Schedule")?></a></li>
    <li><a href="config.php?type=<?php echo urlencode($type)?>&display=<?php echo urlencode($display)?>&action=restore"><?php echo _("Restore from Backup")?></a></li>

<?php 
//get unique account rows for navigation menu
$results = Get_Backup_Sets();

if (isset($results)) {
	foreach ($results as $result) {
		echo "<li><a id=\"".($extdisplay==$result[13] ? 'current':'')."\" href=\"config.php?type=".urlencode($type)."&display=".urlencode($display)."&action=edit&backupid=".urlencode($result[13])."&backupname=".urlencode($result[0])."\">{$result[0]}</a></li>";
	}
}
?>
</ul></div>


<div class="content">

<?php
if ($action == 'add')
{
	?>
	<h2><?php echo _("System Backup")?></h2>
	<form name="addbackup" action="<?php $_SERVER['PHP_SELF'] ?>" method="post">
	<input type="hidden" name="display" value="<?php echo $display?>">
	<input type="hidden" name="type" value="<?php echo $type?>">
	<input type="hidden" name="action" value="addednew">
        <table>
	<?php Show_Backup_Options(); ?>
        </table>
    <h5><?php echo _("Run Schedule")?><hr></h5>
        <table>
	<?php show_schedule("yes",""); ?>
	<tr>
        <td colspan="5" align="center"><input name="Submit" type="submit" value="<?php echo _("Submit Changes")?>" ></td>
        </tr>
        </table>
	</form>
	<br><br><br><br><br>

<?php
}
else if ($action == 'edit')
{
	?>
	<h2><?php echo _("System Backup")?></h2>
	<p><a href="config.php?type=<?php echo urlencode($type)?>&display=<?php echo urlencode($display) ?>&action=delete&backupid=<?php echo urlencode($_REQUEST['backupid']); ?>"><?php echo _("Delete Backup Schedule")?> <?php echo $_REQUEST['backupname']; ?></a></p>
	<form name="addbackup" action="<?php $_SERVER['PHP_SELF'] ?>" method="post">
	<input type="hidden" name="display" value="<?php echo $display?>">
	<input type="hidden" name="action" value="edited">
	<input type="hidden" name="backupid" value="<?php echo $_REQUEST['backupid']; ?>">
	<input type="hidden" name="type" value="<?php echo $type?>">
        <table>
	<?php Show_Backup_Options($_REQUEST['backupid']); ?>
        </table>
    <h5><?php echo _("Run Schedule")?><hr></h5>
        <table>
	<?php show_schedule("yes", "$_REQUEST[backupid]"); ?>
	<tr>
        <td colspan="5" align="center"><input name="Submit" type="submit" value="<?php echo _("Submit Changes")?>" ></td>
        </tr>
        </table>
	</form>
	<br><br><br><br><br>

<?php
}
else if ($action == 'restore')
{
?>
	<h2><?php echo _("System Restore")?></h2>
<?php
	if (empty($dir)) {
		$dir = $asterisk_conf['astvarlibdir']."/backups";
		if(!is_dir($dir)) mkdir($dir);
	}

	Get_Tar_Files($dir, $display, $file);
	echo "<br><br><br><br><br><br><br><br><br><br><br><br>";
	
}
else
{
	if (isset($Message)){
	?>
		<h3><?php echo $Message ?></h3>
	<?php }
	else{
	?>
		<h2><?php echo _("System Backup") ?></h2>
	<?php }
?>

	

	<br><br><br><br><br><br>
	<br><br><br><br><br><br>
<?php
}
?>
