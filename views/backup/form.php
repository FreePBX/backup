<script>
var backup_jobs = [];
var runningRestore=false;
var bkjob_names=[];
<?php
if(empty($id)) {
	$dval[] = ["type" => "dir", "path" => "__ASTETCDIR__", "exclude" => []];
	$custom_files = json_encode($dval, JSON_THROW_ON_ERROR);
}
$items = [];
if(!empty($custom_files)) {
	$items = json_decode((string) $custom_files, true, 512, JSON_THROW_ON_ERROR);
}
$bkjobs = FreePBX::Backup()->listBackups();
if (!empty($bkjobs)) {
	foreach ($bkjobs as $bk) {
		$tmp_name_list[] = $bk['name'];
	}
	echo "bkjob_names = " . json_encode($tmp_name_list, JSON_THROW_ON_ERROR) . ";";
}
?>
</script>

<div class="container-fluid">
	<h1><?php echo sprintf(_("%s Backup %s"),(!empty($id) ? _('Edit') : _('Add')),(!empty($id) ? '"'.$backup_name.'"' : ''))?></h1>
	<div class="row">
		<div class="col-sm-12">
			<div class="fpbx-container">
				<div class="display full-border">
					<form id="addBackupJob" name="addBackupJob" class="fpbx-submit" name="frm_extensions" action="?display=backup" method="post" data-fpbx-delete="config.php?display=backup&type=backup&amp;id=<?php echo $id?>&amp;action=del"
						role="form">
						<input type="hidden" id="id" name="id" value="<?php echo $id ?>">
						<input type="hidden" id="backup_items" name="backup_items" value='unchanged'>
						<input type="hidden" id="backup_schedule" name="backup_schedule" value="<?php echo $backup_schedule ?>">
						<div class="section-title" data-for="backup-basic">
							<h3>
								<i class="fa fa-minus"></i>
								<?php echo _("Basic Information") ?>
							</h3>
						</div>
						<div class="section" data-id="backup-basic">
							<!--Backup Name-->
							<div class="element-container">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="backup_name">
												<?php echo _("Backup Name") ?>
											</label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="backup_name"></i>
										</div>
										<div class="col-md-9">
											<input type="text" class="form-control" id="backup_name" name="backup_name" value="<?php echo $backup_name ?? ''?>">
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="backup_name-help" class="help-block fpbx-help-block">
											<?php echo _("A name used to identify your backups")?>
										</span>
									</div>
								</div>
							</div>
							<!--END Backup Name-->
							<!--Backup Description-->
							<div class="element-container">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="backup_description">
												<?php echo _("Backup Description") ?>
											</label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="backup_description"></i>
										</div>
										<div class="col-md-9">
											<input type="text" class="form-control" id="backup_description" name="backup_description" value="<?php echo $backup_description ?? ''?>">
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="backup_description-help" class="help-block fpbx-help-block">
											<?php echo _("Add a description for your backup")?>
										</span>
									</div>
								</div>
							</div>
							<!--END Backup Description-->
							<!--Backup Items-->
							<div class="element-container">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="backup_items">
												<?php echo _("Backup Items") ?>
											</label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="backup_items"></i>
										</div>
										<div class="col-md-9">
											<a data-toggle="modal" href="#itemsModal" id="backup_modules" name="backup_modules" class="btn btn-lg">
												<?php echo _("Modules")?>
											</a>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="backup_items-help" class="help-block fpbx-help-block">
											<?php echo _("Select items to backup")?>
										</span>
									</div>
								</div>
							</div>
							<!--END Backup Items-->
							<div class="element-container">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="custom_files">
												<?php echo _("Custom Files") ?>
											</label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="custom_files"></i>
										</div>
										<div class="col-md-9">
											<div class="panel panel-default">
												<div class="panel-heading">
													<h3 class="panel-title"><?php echo _("Items")?></h3>
												</div>
												<div class="panel-body">
													<?php echo load_view(__DIR__ . '/item_table.php',['items' => $items, 'immortal' => '']);?>
												</div>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="custom_files-help" class="help-block fpbx-help-block">
											<?php echo _("Add custom files in backup")?>
										</span>
									</div>
								</div>
							</div>
						</div>
						<div class="section-title" data-for="backup-notify">
							<h3>
								<i class="fa fa-minus"></i>
								<?php echo _("Notifications") ?>
							</h3>
						</div>
						<div class="section" data-id="backup-notify">
							<!--Notification Email-->
							<div class="element-container">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="backup_email">
												<?php echo _("Notification Email") ?>
											</label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="backup_email"></i>
										</div>
										<div class="col-md-9">
											<input type=text class="form-control" id="backup_email" name="backup_email" value="<?php echo $backup_email ?? ''?>">
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="backup_email-help" class="help-block fpbx-help-block">
											<?php echo _("Email address to send notifications, Multiple email addresses need to be separated by comma")?>
										</span>
									</div>
								</div>
							</div>
							<!--END Notification Email-->
							<!--Notification Email-->
							<div class="element-container">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="backup_emailinline">
												<?php echo _("Inline Logs") ?>
											</label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="backup_emailinline"></i>
										</div>
										<div class="col-md-9">
											<span class="radioset">
												<?php $backup_emailinline ??= 'no';?>
												<input type="radio" name="backup_emailinline" id="backup_emailinline_success" value="yes" <?php echo ($backup_emailinline === 'yes'
													? "CHECKED": "") ?>>
												<label for="backup_emailinline_success">
													<?php echo _("Yes");?>
												</label>
												<input type="radio" name="backup_emailinline" id="backup_emailinline_failure" value="no" <?php echo ($backup_emailinline === 'no'
													? "CHECKED": "") ?>>
												<label for="backup_emailinline_failure">
													<?php echo _("No");?>
												</label>
											</span>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="backup_emailinline-help" class="help-block fpbx-help-block">
											<?php echo _("When set to Yes logs will be added to the body of the email, when set to No logs will be added as an attachment")?>
										</span>
									</div>
								</div>
							</div>
							<!--Notification Email-->
							<div class="element-container">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="backup_emailtype">
												<?php echo _("Email Type") ?>
											</label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="backup_emailtype"></i>
										</div>
										<div class="col-md-9">
											<span class="radioset">
												<?php $backup_emailtype ??= 'both';?>
												<input type="radio" name="backup_emailtype" id="backup_emailtype_success" value="success" <?php echo ($backup_emailtype=="success"
													? "CHECKED": "") ?>>
												<label for="backup_emailtype_success">
													<?php echo _("Success");?>
												</label>
												<input type="radio" name="backup_emailtype" id="backup_emailtype_failure" value="failure" <?php echo ($backup_emailtype=="failure"
													? "CHECKED": "") ?>>
												<label for="backup_emailtype_failure">
													<?php echo _("Failure");?>
												</label>
												<input type="radio" name="backup_emailtype" id="backup_emailtype_both" value="both" <?php echo ($backup_emailtype=="both"
													? "CHECKED": "") ?>>
												<label for="backup_emailtype_both">
													<?php echo _("Both");?>
												</label>
											</span>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="backup_emailtype-help" class="help-block fpbx-help-block">
											<?php echo _("When to email")?>
										</span>
									</div>
								</div>
							</div>
							<!--END Notification Email-->
						</div>
						<div class="section-title" data-for="backup-storage">
							<h3>
								<i class="fa fa-minus"></i>
								<?php echo _("Storage") ?>
							</h3>
						</div>
						<div class="section" data-id="backup-storage">
							<!--Storage Location-->
							<div class="element-container">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="backup_storage">
												<?php echo _("Storage Location") ?>
											</label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="backup_storage"></i>
										</div>
										<div class="col-md-9">
											<select class="form-control" id="backup_storage" name="backup_storage[]" multiple="multiple">
												<?php echo $storageopts ?? "" ?>
											</select>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="backup_storage-help" class="help-block fpbx-help-block">
											<?php echo _("Select one or more storage locations. Storage locations can be added/configured with the Filestore module")?>
										</span>
									</div>
								</div>
							</div>
							<!--END Storage Location-->
							<div class="element-container">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="backup_addbjname">
												<?php echo _("Append Backup Name as a directory to the Storage path") ?>
											</label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="backup_addbjname"></i>
										</div>
										<div class="col-md-9">
											<span class="radioset">
<?php $backup_addbjname = (!empty($backup_addbjname) && isset($backup_addbjname))?$backup_addbjname:'no';?>
<input type="radio" name="backup_addbjname" id="backup_addbjname_success" value="yes" <?php echo ($backup_addbjname === 'yes' ? "CHECKED": "") ?>>
												<label for="backup_addbjname_success">
													<?php echo _("Yes");?>
												</label>
<input type="radio" name="backup_addbjname" id="backup_addbjname_failure" value="no" <?php echo ($backup_addbjname === 'no' ? "CHECKED": "") ?>>
												<label for="backup_addbjname_failure">
													<?php echo _("No");?>
												</label>
											</span>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="backup_addbjname-help" class="help-block fpbx-help-block">
											<?php echo _("When set to Yes , Backp files will store like 'filestore-path/backup-job-name/backup-file' and if set to NO then backup file will store into 'filestore-path/backup-file'");?>			</span>
									</div>
								</div>
							</div>

						</div>
						<!--END Storage Location Section -->
						<div class="section-title" data-for="backup-schedule">
							<h3>
								<i class="fa fa-minus"></i>
								<?php echo _("Schedule and Maintenance") ?>
							</h3>
						</div>
						<div class="section" data-id="backup-schedule">
							<!--Enabled-->
							<div class="element-container">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="schedule_enabled">
												<?php echo _("Enabled") ?>
											</label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="schedule_enabled"></i>
										</div>
										<div class="col-md-9 radioset">
											<input type="radio" name="schedule_enabled" id="schedule_enabledyes" value="yes" <?php echo (isset($schedule_enabled) && $schedule_enabled=="yes" ?
												"CHECKED": "") ?>>
											<label for="schedule_enabledyes">
												<?php echo _("Yes");?>
											</label>
											<input type="radio" name="schedule_enabled" id="schedule_enabledno" <?php echo (isset($schedule_enabled) && $schedule_enabled=="yes" ? "": "CHECKED")
												?>>
											<label for="schedule_enabledno">
												<?php echo _("No");?>
											</label>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="schedule_enabled-help" class="help-block fpbx-help-block">
											<?php echo _("Enable scheduled backups")?>
										</span>
									</div>
								</div>
							</div>
							<!--END Enabled-->
							<!--Scheduleing-->
							<div class="element-container">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="backup_schedule">
												<?php echo _("Scheduling") ?>
											</label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="backup_schedule"></i>
										</div>
										<div class="col-md-9">
											<div class="cron-ui"></div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="backup_schedule-help" class="help-block fpbx-help-block">
											<?php echo _("When should this backup run")?>
										</span>
									</div>
								</div>
							</div>
							<!--END Scheduleing-->
						</div>
						<div class="section-title" data-for="backup-maint">
							<h3>
								<i class="fa fa-minus"></i>
								<?php echo _("Maintenance") ?>
							</h3>
						</div>
						<div class="section" data-id="backup-schedule">
							<!--Delete After Runs-->
							<div class="element-container">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="maintruns">
												<?php echo _("Delete After Runs") ?>
											</label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="maintruns"></i>
										</div>
										<div class="col-md-9">
											<input type="number" min="0" class="form-control" id="maintruns" name="maintruns" value="<?php echo $maintruns ?? 0?>">
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="maintruns-help" class="help-block fpbx-help-block">
											<?php echo _("How many updates to keep. If this number is 3, the last 3 will be kept. 0 is unlimited")?>
										</span>
									</div>
								</div>
							</div>
							<!--END Delete After Runs-->
							<!--Delete After Days-->
							<div class="element-container">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="maintage">
												<?php echo _("Delete After Days") ?>
											</label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="maintage"></i>
										</div>
										<div class="col-md-9">
											<?php $maintage ??= ""; ?>
											<select class="form-control" id="maintage" name="maintage">
												<option>
													<?php echo _("Unlimited")?>
												</option>
												<option value="7" <?php echo ($maintage==7 )? 'SELECTED': ''?>>
													<?php echo sprintf(_("%s Days"),'7')?>
												</option>
												<option value="14" <?php echo ($maintage==14)? 'SELECTED': ''?>>
													<?php echo sprintf(_("%s Days"),'14')?>
												</option>
												<option value="21" <?php echo ($maintage==21)? 'SELECTED': ''?>>
													<?php echo sprintf(_("%s Days"),'21')?>
												</option>
												<option value="30" <?php echo ($maintage==30)? 'SELECTED': ''?>>
													<?php echo sprintf(_("%s Days"),'30')?>
												</option>
												<option value="60" <?php echo ($maintage==60)? 'SELECTED': ''?>>
													<?php echo sprintf(_("%s Days (%s Months)"),'60','2')?>
												</option>
												<option value="90" <?php echo ($maintage==90)? 'SELECTED': ''?>>
													<?php echo sprintf(_("%s Days (%s Months)"),'90','3')?>
												</option>
												<option value="120" <?php echo ($maintage==120)? 'SELECTED': ''?>>
													<?php echo sprintf(_("%s Days (%s Months)"),'120','4')?>
												</option>
												<option value="250" <?php echo ($maintage==250)? 'SELECTED': ''?>>
													<?php echo sprintf(_("%s Days (%s Months)"),'240','8')?>
												</option>
												<option value="365" <?php echo ($maintage==365)? 'SELECTED': ''?>>
													<?php echo sprintf(_("%s Days (%s Months)"),'365','12')?>
												</option>
											</select>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="maintage-help" class="help-block fpbx-help-block">
											<?php echo _("How long to maintain backups. Example 30 will delete anything older than 30 days.")?>
										</span>
									</div>
								</div>
							</div>
						</div>
						<!--END Delete After Days-->
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
														<input type="text" class="form-control" id="prebu_hook" name="prebu_hook" value="<?php echo $prebu_hook ?? ''?>">
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
														<input type="text" class="form-control" id="postbu_hook" name="postbu_hook" value="<?php echo $postbu_hook ?? ''?>">
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
														<input type="text" class="form-control" id="prere_hook" name="prere_hook" value="<?php echo $prere_hook ?? ''?>">
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
														<input type="text" class="form-control" id="postre_hook" name="postre_hook" value="<?php echo $postre_hook ?? ''?>">	
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

						<?php echo $warmspare ?>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
<script type="text/javascript">
	$(document).ready(function () {
		var schedule = $('.cron-ui').cronui({
			dropDownMultiple: true,
			resultOutputId: 'backup_schedule',
			dropDownSizeClass: 'col-md-2',
			initial: '<?php echo $backup_schedule?>'
		});
		//We don't want every minute... like ever
		$('option[value="minute"]').hide();
	});
</script>

<div class="modal fade" tabindex="-99" id="itemsModal" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
				<h4 class="modal-title">
					<?php echo _("Modules to Backup")?>
				</h4>
			</div>
			<div class="modal-body">
				<?php
				$dataurl = "ajax.php?module=backup&command=backupItems&id=".$id;
				?>
					<table id="backupmodules"
						data-url="<?php echo $dataurl?>"
						data-toggle="table"
						data-search="true"
						data-id-field="modulename"
						data-detail-view="true"
						data-detail-formatter="moduleSettingFormatter"
						data-detail-filter="moduleSettingFilter"
						data-maintain-meta-data="true"
						data-escape="true" 
						class="table table-striped"
					>
						<thead>
							<tr>
								<th data-field="selected" data-checkbox='true'>
									<?php echo _("Selected")?>
								</th>
								<th data-field="display">
									<?php echo _("Module")?>
								</th>
							</tr>
						</thead>
					</table>
			</div>
			<div class="modal-footer">
				<a class="btn btn-default" id="itemsReset">
					<?php echo _("Reset")?>
				</a>
				<a class="btn btn-default" data-dismiss="modal">
					<?php echo _("Close")?>
				</a>
				<a class="btn btn-primary" id="itemsSave">
					<?php echo _("Save Changes")?>
				</a>
			</div>
		</div>
	</div>
</div>
