<?php
$action = isset($_REQUEST['action'])?$_REQUEST['action']:'';
$display='backup';
$type = 'tool';

$dir=isset($_REQUEST['dir'])?$_REQUEST['dir']:'';
$extdisplay=isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:'';
$file=isset($_REQUEST['file'])?$_REQUEST['file']:'';
$filetype=isset($_REQUEST['filetype'])?$_REQUEST['filetype']:'';
$id=isset($_REQUEST['backupid'])?$_REQUEST['backupid']:'';

// Santity check passed params
if (strstr($dir, '..') || strpos($dir, '\'') || strpos($dir, '"') || strpos($dir, '\'') || strpos($dir,'\`') ||
    strstr($file, '..') || strpos($file, '\'') || strpos($file, '"') || strpos($file, '\'') || strpos($file,'\`') ||
    strpos($id, '.') || strpos($id, '\'') || strpos($id, '"') || strpos($id, '\'') || strpos($id,'\`') ||
    strpos($filetype, '.') || strpos($filetype, '\'') || strpos($filetype, '"') || strpos($filetype, '\'') || strpos($filetype,'\`')) {
	print "You're trying to use an invalid character. Please don't.\n";
	exit;
}

switch ($action) {
	case "edited":
		backup_delete_set($id);
	case "addednew":
		$parms=array('all_days','all_months','all_weekdays','backup_schedule', 'name',
								'mins','hours','days','months','weekdays','voicemail',
								'recordings','configurations','cdr','fop', 'ftpuser',
								'ftppass','ftphost','ftpdir','sshuser','sshkey','sshhost','sshdir',
								'emailaddr','emailmaxsize','emailmaxtype','admin','include','exclude',
								'remotesshhost','remotesshuser','remotesshkey','remoterestore','sudo',
								'overwritebackup');
		foreach($parms as $p){
			$backup_parms[$p]=(isset($_REQUEST[$p]))?$_REQUEST[$p]:'';
			}
		if($backup_parms['name']==''){$backup_parms['name']='backup'.rand(000,999);}//handel missing names gracefully
		backup_save_schedule(backup_get_string($backup_parms));
	break;
	case "delete":
		backup_delete_set($id);
	break;
	case "deletedataset":
		exec("/bin/rm -rf '$dir'");
	break;
	case "deletefileset":
		exec("/bin/rm -rf '$dir'");
	break;
	case "restored":
		$message=backup_restore_tar($dir, $file, $filetype, $display);
		// Regenerate all the ASTDB stuff. Note, we need a way to do speedials and other astdb stuff here.
    // TODO: should this be doing a redirect_standard, doesn't that avoid the status result?
		needreload();
		redirect_standard();
	break;
}

if ($action == 'addxx')
{
	?>
	
	<form name="addbackup" action="<?php $_SERVER['PHP_SELF'] ?>" method="post">
	<input type="hidden" name="display" value="<?php echo $display?>">
	<input type="hidden" name="type" value="<?php echo $type?>">
	<input type="hidden" name="action" value="addednew">
        <table>
	<?php backup_showopts(); ?>
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
else if ($action == 'editxx')
{
	?>
	
<?php
	$delURL = $_SERVER['PHP_SELF'].'?type='.urlencode($type).'&display='.urlencode($display).'&action=delete&backupid='.urlencode($_REQUEST['backupid']);
	$tlabel = sprintf(_("Delete Backup Schedule %s"),$_REQUEST['backupname']);
	$label = '<span><img width="16" height="16" border="0" title="'.$tlabel.'" alt="" src="images/core_delete.png"/>&nbsp;'.$tlabel.'</span>';
?>
	<p><a href="<?php echo $delURL ?>"><?php echo $label; ?></a></p>

	<form name="addbackup" action="<?php $_SERVER['PHP_SELF'] ?>" method="post">
	<input type="hidden" name="display" value="<?php echo $display?>">
	<input type="hidden" name="action" value="edited">
	<input type="hidden" name="backupid" value="<?php echo $_REQUEST['backupid']; ?>">
	<input type="hidden" name="type" value="<?php echo $type?>">
        <table>
	<?php backup_showopts($_REQUEST['backupid']); ?>
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
	<script language="javascript" type="text/javascript">
		$(document).ready(function() {
			$("[name='Submit']").click(function(){
				if($("[name='name']").val().split(' ').length > 1){
					alert('<?php echo _('Backup names cannot contain spaces.'); ?>');
				return false;
				}
			})
		});
	</script>
<?php
}
else if ($action == 'restorexx')
{
?>
	<h2><?php echo _("System Restore")?></h2>
<?php
	if (empty($dir)) {
		$dir = $asterisk_conf['astvarlibdir']."/backups";
		if(!is_dir($dir)) mkdir($dir);
	}
	backup_list_files($dir, $display, $file);
}
else
{
  //TODO: test to see if any of this is printed or if the above redirect_standard needs to be removed
  if (isset($message) && is_array($message)) {
    echo '<h3>'._('ERROR Restoring Backup Set')."</h3>\n";
    echo "<ul>\n";
    foreach ($message as $error) {
      echo '<li>'.sprintf(_('%s (Return Code: %s)'),$error['description'],$error['ret'])."</li>\n";
    }
    echo "<\ul>\n";
  } elseif (isset($message)){
	?>
		<h3><?php echo $message ?></h3>
	<?php }
	else{
	?>
		
	<?php }
?>

	

	<br><br><br><br><br><br>
	<br><br><br><br><br><br>
<?php
}
?>