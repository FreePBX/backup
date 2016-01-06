<?php
if (!isset($id)) {
	$id = "";
}
?>
<h2><?php echo _("Backup")?></h2>
<form class="fpbx-submit" name="backup_form" action="" method="post" id="backup_form" data-fpbx-delete="?display=backup&action=delete&id=<?php echo $id; ?>">
	<input type="hidden" name="action" value="save">
	<input type="hidden" name="id" value="<?php echo $id; ?>">
	<!--Backup Name-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="name"><?php echo _("Backup Name") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="name"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="name" name="name" value="<?php echo isset($name)?$name:''?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="name-help" class="help-block fpbx-help-block"><?php echo _("Name this backup")?></span>
			</div>
		</div>
	</div>
	<!--END Backup Name-->
	<!--Description-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="desc"><?php echo _("Description") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="desc"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="desc" name="desc" value="<?php echo isset($desc)?$desc:''?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="desc-help" class="help-block fpbx-help-block"><?php echo _("Description or notes for this backup")?></span>
			</div>
		</div>
	</div>
	<!--END Description-->
	<!--Status Email-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="email"><?php echo _("Status Email") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="email"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="email" name="email" value="<?php echo isset($email)?$email:''?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="email-help" class="help-block fpbx-help-block"><?php echo _("Email to send status messages to when this task is run")?></span>
			</div>
		</div>
	</div>
	<!--END Status Email-->
<div class="row">
	<div class="col-md-12">
		<div class="well well-default">
			<?php echo _('Drag templates and drop them in the items table to add the templates items to the table')?>
		</div>
	</div>
	<div class="col-md-8">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title"><?php echo _("Items")?></h3>
			</div>
			<div class="panel-body">
				<div id="items_over"><?php echo _("Drop Here")?></div>
				<?php echo load_view(dirname(__FILE__) . '/../item_table.php',array('items' => $items, 'immortal' => ''));?>
			</div>
		</div>
	</div>
	<div class="col-md-4">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title"><?php echo _("Templates")?></h3>
			</div>
			<div class="panel-body">
				<ul id="templates" class="sortable">
					<?php
					foreach ($templates as $t) {
						echo'<li data-template="' . rawurlencode(json_encode($t['items'])) . '"'
										. ' title="' . $t['desc'] . '"'
										.'>'
										. '<a>'
										. '<i class="fa fa-arrows"></i>'
										. $t['name']
										. '</a>'
										. '</li>';
					}

					?>
				</ul>
			</div>
		</div>
	</div>
</div>

<div class="section-title" data-for="buhooks"><h3><i class="fa fa-minus"></i> <?php echo _("Hooks")?></h3></div>
<div class="section" data-id="buhooks">
	<!--Pre-Backup Hook-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="prebu_hook"><?php echo _("Pre-Backup Hook") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="prebu_hook"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="prebu_hook" name="prebu_hook" value="<?php echo isset($prebu_hook)?$prebu_hook:''?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="prebu_hook-help" class="help-block fpbx-help-block"><?php echo _("A script to be run BEFORE a backup is started.")?></span>
			</div>
		</div>
	</div>
	<!--END Pre-Backup Hook-->
	<!--Post-Backup Hook-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="postbu_hook"><?php echo _("Post-Backup Hook") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="postbu_hook"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="postbu_hook" name="postbu_hook" value="<?php echo isset($postbu_hook)?$postbu_hook:''?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="postbu_hook-help" class="help-block fpbx-help-block"><?php echo _("A script to be run AFTER a backup is completed.")?></span>
			</div>
		</div>
	</div>
	<!--END Post-Backup Hook-->
	<!--Pre-Restore Hook-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="prere_hook"><?php echo _("Pre-Restore Hook") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="prere_hook"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="prere_hook" name="prere_hook" value="<?php echo isset($prere_hook)?$prere_hook:''?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="prere_hook-help" class="help-block fpbx-help-block"><?php echo _("A script to be run BEFORE a backup is restored.")?></span>
			</div>
		</div>
	</div>
	<!--END Pre-Restore Hook-->
	<!--Post-Restore Hook-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="postre_hook"><?php echo _("Post-Restore Hook") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="postre_hook"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="postre_hook" name="postre_hook" value="<?php echo isset($postre_hook)?$postre_hook:''?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="postre_hook-help" class="help-block fpbx-help-block"><?php echo _("A script to be run AFTER a backup is restored.")?></span>
			</div>
		</div>
	</div>
	<!--END Post-Restore Hook-->
</div>
<?php
$serveropts = '<option value = "0">'._('This server').'</option>';
foreach ($servers as $s) {
	if ($s['type'] == 'ssh') {
		$selected = ($s['id'] == $bu_server)?'SELECTED':'';
		$serveropts .= '<option value='.$s['id'].' '.$selected.'>'.$s['name'].'</option>';
	}
}
?>
<!--Backup Server-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="bu_server"><?php echo _("Backup Server") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="bu_server"></i>
					</div>
					<div class="col-md-9">
						<select class="form-control" id="bu_server" name="bu_server">
							<?php echo $serveropts?>
						</select>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="bu_server-help" class="help-block fpbx-help-block"><?php echo _("Select the server to be backed up (this server, or any other SSH server)")?></span>
		</div>
	</div>
</div>
<!--END Backup Server-->
<!--Restore Here-->

<div class="element-container remote">
	<div class="row">
		<div class="form-group">
			<div class="col-md-3">
				<label class="control-label" for="restore"><?php echo _("Restore Here") ?></label>
				<i class="fa fa-question-circle fpbx-help-icon" data-for="restore"></i>
			</div>
<?php
if ($restore == "true" || $restore == "on") {
	$restyes = "checked";
	$restno = "";
} else {
	$restyes = "";
	$restno = "checked";
}
?>
			<div class="col-md-9 radioset">
				<input type="radio" name="restore" id="restoreyes" value="on" <?php echo $restyes; ?>>
				<label for="restoreyes"><?php echo _("Yes");?></label>
				<input type="radio" name="restore" id="restoreno" value="off" <?php echo $restno; ?>>
				<label for="restoreno"><?php echo _("No");?></label>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="restore-help" class="help-block fpbx-help-block"><?php echo _("Restore backup to this server after the backup is complete")?></span>
		</div>
	</div>
</div>
<!--END Restore Here-->
<!--Disable Registered Trunks-->
<div class="element-container remote restore">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="disabletrunks"><?php echo _("Disable Registered Trunks") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="disabletrunks"></i>
					</div>
					<div class="col-md-9 radioset">
            <input type="radio" name="disabletrunks" id="disabletrunksyes" value="true" <?php echo ($disabletrunks == "true"?"CHECKED":"") ?>>
            <label for="disabletrunksyes"><?php echo _("Yes");?></label>
            <input type="radio" name="disabletrunks" id="disabletrunksno" <?php echo ($disabletrunks == "true"?"":"CHECKED") ?>>
            <label for="disabletrunksno"><?php echo _("No");?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="disabletrunks-help" class="help-block fpbx-help-block"><?php echo _("After a restore, disable any trunks that use registration. This is helpful to prevent the Primary and Standby systems from \"fighting\" for the registration, resulting in some calls routed to the Standby system.")?></span>
		</div>
	</div>
</div>
<!--END Disable Registered Trunks-->
<!--Exclude NAT settings-->
<div class="element-container remote restore">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="skipnat"><?php echo _("Exclude NAT settings") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="skipnat"></i>
					</div>
					<div class="col-md-9 radioset">
            <input type="radio" name="skipnat" id="skipnatyes" value="true" <?php echo ($skipnat == "true"?"CHECKED":"") ?>>
            <label for="skipnatyes"><?php echo _("Yes");?></label>
            <input type="radio" name="skipnat" id="skipnatno" <?php echo ($skipnat == "true"?"":"CHECKED") ?>>
            <label for="skipnatno"><?php echo _("No");?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="skipnat-help" class="help-block fpbx-help-block"><?php echo _("Explicitly exclude any machine-specific IP settings. This allows you to have a warm-spare machine with a different IP address.")?></span>
		</div>
	</div>
</div>
<!--END Exclude NAT settings-->
<!--Apply Configs-->
<div class="element-container remote restore">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="applyconfigs"><?php echo _("Apply Configs") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="applyconfigs"></i>
					</div>
					<div class="col-md-9 radioset">
            <input type="radio" name="applyconfigs" id="applyconfigsyes" value="true" <?php echo ($applyconfigs == "true"?"CHECKED":"") ?>>
            <label for="applyconfigsyes"><?php echo _("Yes");?></label>
            <input type="radio" name="applyconfigs" id="applyconfigsno" <?php echo ($applyconfigs == "true"?"":"CHECKED") ?>>
            <label for="applyconfigsno"><?php echo _("No");?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="applyconfigs-help" class="help-block fpbx-help-block"><?php echo _("Equivalence of clicking the red button, will happen automatically after a restore on a Standby system")?></span>
		</div>
	</div>
</div>
<!--END Apply Configs-->
<?php
foreach ($storage_servers as $s) {
	echo '<input type="hidden" name="storage_servers[]" value="' . $s . '">';
}
?>
<div class="row">
	<div class="col-md-12">
		<div class="well well-default">
			<?php echo _('Drag servers from the Available Servers list to add them as Storage Servers')?>
		</div>
	</div>
	<div class="col-md-6">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title"><?php echo _("Storage Servers")?></h3>
			</div>
			<div class="panel-body">
				<ul id="storage_used_servers" class="sortable storage_servers">
					<?php
					foreach ($storage_servers as $idx => $s) {
						echo '<li data-server-id="' . $servers[$s]['id'] . '">'
										. '<a href="#">'
										. '<i class="fa fa-arrows"></i>'
										. $servers[$s]['name']
										. ' (' . $servers[$s]['type'] . ')'
										. '</a>'
										. '</li>';
						unset($servers[$s]);
					}
					?>
				</ul>
			</div>
		</div>
	</div>
	<div class="col-md-6">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title"><?php echo _("Available Servers")?></h3>
			</div>
			<div class="panel-body">
				<ul id="storage_avail_servers" class="sortable storage_servers">
					<?php
					foreach ($servers as $s) {
						if (in_array($s['type'], array('ftp', 'ssh', 'email', 'local', 'awss3'))) {
							echo '<li data-server-id="' . $s['id'] . '">'
											. '<a href="#">'
											. '<i class="fa fa-arrows"></i>'
											. $s['name']
											. ' (' . $s['type'] . ')'
											. '</a>'
											. '</li>';
						}
					}
					?>
				</ul>
			</div>
		</div>
	</div>
</div>
<div class="section-title" data-for="bucron"><h3><i class="fa fa-minus"></i> <?php echo _("Backup Schedule")?></h3></div>
<div class="section" data-id="bucron">
    <?php
		$cron = array(
			'cron_dom'			=> $cron_dom,
			'cron_dow'			=> $cron_dow,
			'cron_hour'			=> $cron_hour,
			'cron_minute'		=> $cron_minute,
			'cron_month'		=> $cron_month,
			'cron_random'		=> $cron_random,
			'cron_schedule'		=> $cron_schedule
		);
		echo load_view(dirname(__FILE__) . '/../cron.php', $cron);
		?>
</div>
<div class="section-title" data-for="bumaint"><h3><i class="fa fa-minus"></i> <?php echo _("Maintenance")?></h3></div>
<div class="section" data-id="bumaint">
<!--Delete After-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="delete_time"><?php echo _("Delete After") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="delete_time"></i>
					</div>
					<div class="col-md-9">
						<input type="number" min="0" class="form-control" id="delete_time" name="delete_time" value="<?php echo (!empty($delete_time)?$delete_time:'0')?>">
						<div class="radioset">
							<?php $delete_time_type = (!empty($delete_time_type)?$delete_time_type:'days');?>
							<input type="radio" name="delete_time_type" id="delete_time_type_minutes" value="minutes" <?php echo ($delete_time_type == 'minutes'?'CHECKED':'')?>>
							<label for="delete_time_type_minutes"><?php echo _("Minutes")?></label>
							<input type="radio" name="delete_time_type" id="delete_time_type_hours" value="hours" <?php echo ($delete_time_type == 'hours'?'CHECKED':'')?>>
							<label for="delete_time_type_hours"><?php echo _("Hours")?></label>
							<input type="radio" name="delete_time_type" id="delete_time_type_days" value="days" <?php echo ($delete_time_type == 'days'?'CHECKED':'')?>>
							<label for="delete_time_days"><?php echo _("Days")?></label>
							<input type="radio" name="delete_time_type" id="delete_time_type_weeks" value="weeks" <?php echo ($delete_time_type == 'weeks'?'CHECKED':'')?>>
							<label for="delete_time_type_weeks"><?php echo _("Weeks")?></label>
							<input type="radio" name="delete_time_type" id="delete_time_type_months" value="months" <?php echo ($delete_time_type == 'months'?'CHECKED':'')?>>
							<label for="delete_time_type_months"><?php echo _("Months")?></label>
							<input type="radio" name="delete_time_type" id="delete_time_type_years" value="years" <?php echo ($delete_time_type == 'years'?'CHECKED':'')?>>
							<label for="delete_time_type_years"><?php echo _("Years")?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="delete_time-help" class="help-block fpbx-help-block"><?php echo _("Delete this backup after X amount of minutes/hours/days/weeks/months/years. Please note that deletes aren't time based and will only happen after a backup was run. Setting the value to 0 will disable any deleting")?></span>
		</div>
	</div>
</div>
<!--END Delete After-->
<!--Delete After-->
<div class="element-container">
	<div class="row">
		<div class="form-group">
			<div class="col-md-3">
				<label class="control-label" for="delete_amount"><?php echo _("Delete After") ?></label>
				<i class="fa fa-question-circle fpbx-help-icon" data-for="delete_amount"></i>
			</div>
			<div class="col-md-9">
				<div class="input-group">
					<input type="number" min="0" class="form-control" id="delete_amount" name="delete_amount" value="<?php echo ($delete_amount)?$delete_amount:'0'; ?>">
					<span class="input-group-addon" id="runs-addon"><?php echo _("Runs")?></span>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="delete_amount-help" class="help-block fpbx-help-block"><?php echo _("Delete this backup after X amount of runs. Setting the value to 0 will disable any deleting")?></span>
		</div>
	</div>
</div>
<!--END Delete After-->
</div>
</form>
<script type="text/javascript" src="modules/backup/assets/js/views/backup.js"></script>
