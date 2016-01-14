<div id="toolbar-backup">
  <a href="#" role="button" class="btn btn-large btn-primary" data-toggle="modal" data-target="#merlin"><i class="fa fa-magic"></i> <?php echo _('Backup Wizard') ?></a>
  <a href="config.php?type=setup&display=backup&action=edit" role="button" class="btn btn-large btn-primary" ><i class="fa fa-plus"></i> <?php echo _('New Backup') ?></a>
</div>

<table id="backupsGrid" data-toolbar="#toolbar-backup" data-pagination="true" data-search="true" data-url="ajax.php?module=backup&command=getJSON&jdata=backupGrid" data-cache="false" data-toggle="table" class="table table-striped">
    <thead>
            <tr>
            <th data-field="name" data-sortable="true"><?php echo _("Item")?></th>
            <th data-field="description"><?php echo _("Description")?></th>
            <th data-field="id,immortal" data-formatter="linkFormatter"><?php echo _("Actions")?></th>
        </tr>
    </thead>
</table>

<div class="modal fade" id="merlin" tabindex="-1" role="dialog" aria-labelledby="merlin" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<form method="POST" acton="" id="idwizform">
		<input type="hidden" name="action" value="wizard">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">X</button>
				<h4 class="modal-title" id="myModalLabel"><?php echo sprintf(_("%s Backup Wizard"),$brand)?></h4>
			</div>
			<div class="modal-body swMain" id="wizard">
				<ul>
					<li>
						<a href="#welcome">
							<label class="stepNumber">0</label>
							<span class="stepDesc">
								<?php echo _("Introduction")?>
							</span>
						</a>
					</li>
					<li>
						<a href="#step-1">
							<label class="stepNumber">1</label>
							<span class="stepDesc">
								<?php echo _("Step 1")?><br />
								<small><?php echo _("Basic Information")?></small>
							</span>
						</a>
					</li>
					<li>
						<a href="#step-2">
							<label class="stepNumber">2</label>
							<span class="stepDesc">
								<?php echo _("Step 2")?><br />
								<small><?php echo _("Backup Frequency")?></small>
							</span>
						</a>
					</li>
					<li>
						<a href="#step-3">
							<label class="stepNumber">3</label>
							<span class="stepDesc">
								<?php echo _("Step 3")?><br />
								<small><?php echo _("Backup Items")?></small>
							</span>
						</a>
					</li>
					<li>
						<a href="#step-4">
							<label class="stepNumber">4</label>
							<span class="stepDesc">
								<?php echo _("Step 4")?><br />
								<small><?php echo _("Notifications")?></small>
							</span>
						</a>
					</li>
					<li>
						<a href="#step-5">
							<label class="stepNumber">5</label>
							<span class="stepDesc">
								<?php echo _("Step 5")?><br />
								<small><?php echo _("Destination")?></small>
							</span>
						</a>
					</li>
				</ul>
				<div id="welcome">
					<h2 class="StepTitle"><?php echo _("Welcome to the Backup Wizard")?> </h2>
					</br>
					<p><?php echo sprintf(_("This wizard can be used to add a basic backup task for your %s system"),$brand)?></p>
					<p><?php echo _("Click 'Next' to continue.")?></p>
				</div>
				<div id="step-1">
					<h2 class="StepTitle"><?php echo _("Basic Backup Information")?></h2>
					<br/>
					<!--Backup Name-->
					<div class="element-container">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="wizname"><?php echo _("Backup Name") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="wizname"></i>
										</div>
										<div class="col-md-9">
											<input type="text" class="form-control" id="wizname" name="wizname" value="">
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="wizname-help" class="help-block fpbx-help-block"><?php echo _("Give your backup a friendly name")?></span>
							</div>
						</div>
					</div>
					<!--END Backup Name-->
					<!--Backup Description-->
					<div class="element-container">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="wizdesc"><?php echo _("Backup Description") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="wizdesc"></i>
										</div>
										<div class="col-md-9">
											<input type="text" class="form-control" id="wizdesc" name="wizdesc" value="">
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="wizdesc-help" class="help-block fpbx-help-block"><?php echo _("Give your backup a description")?></span>
							</div>
						</div>
					</div>
					<!--END Backup Description-->
				</div>
				<div id="step-2">
					<h2 class="StepTitle"><?php echo _("How often should we backup?")?></h2>
					<br/>
					<table>
						<tr>
							<td>
								<span class="radioset">
								<input type="radio" name="wizfreq" id="wizfreqnightly" value="daily" CHECKED>
								<label for="wizfreqnightly"><?php echo _("Nightly");?></label>
								<input type="radio" name="wizfreq" id="wizfreqweekly" value="weekly">
								<label for="wizfreqweekly"><?php echo _("Weekly");?></label>
								<input type="radio" name="wizfreq" id="wizfreqmonthly" value="monthly">
								<label for="wizfreqmonthly"><?php echo _("Monthly");?></label>
								</span>
							</td>
							<td id="atlabel"><b><?php echo _("AT")?></b></td>
							<td id="atinput">
								<div class="input-group">
									<input type="number" min="0" max="23" class="form-control" id="wizat" name="wizat" value="23">
									<span class="input-group-addon" id="wizat-addon">:00</span>
								</div>
							</td>
							<td id="onlabel">
							</td>
							<td id ="oninput">
							</td>
						</tr>
					</table>
				</div>
				<div id="step-3">
					<h2 class="StepTitle"><?php echo _("What should we backup?")?></h2>
					<br/>
					<p><?php echo _("All PBX Configuration files and any Custom Sound Prompts are included in the base backup")?></p>
					<!--Backup Voicemails-->
					<div class="element-container">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="wizitemvm"><?php echo _("Voicemails") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="wizitemvm"></i>
										</div>
										<div class="col-md-9 radioset">
											<input type="radio" name="wizvm" id="wizvmyes" value="yes" CHECKED>
											<label for="wizvmyes"><?php echo _("Yes");?></label>
											<input type="radio" name="wizvm" id="wizvmno" value="no">
											<label for="wizvmno"><?php echo _("No");?></label>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="wizitemvm-help" class="help-block fpbx-help-block"><?php echo _("Would you like voicemails backded up.")?></span>
							</div>
						</div>
					</div>
					<!--END Backup Voicemails-->
					<!--Backup Recordings-->
					<div class="element-container">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="wizrecording"><?php echo _("Call Recordings") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="wizrecording"></i>
										</div>
										<div class="col-md-9 radioset">
											<input type="radio" name="wizbu" id="wizbuyes" value="yes" CHECKED>
											<label for="wizbuyes"><?php echo _("Yes");?></label>
											<input type="radio" name="wizbu" id="wizbuno" value="no">
											<label for="wizbuno"><?php echo _("No");?></label>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="wizrecording-help" class="help-block fpbx-help-block"><?php echo _("Would you like to backup recordings?")?></span>
							</div>
						</div>
					</div>
					<!--END Backup Recordings-->
					<!--Backup CDR and Call Log Data-->
					<div class="element-container">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="wizcdr"><?php echo _("CDR and Call Log Data") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="wizcdr"></i>
										</div>
										<div class="col-md-9 radioset">
											<input type="radio" name="wizcdr" id="wizcdryes" value="yes" CHECKED>
											<label for="wizcdryes"><?php echo _("Yes");?></label>
											<input type="radio" name="wizcdr" id="wizcdrno" value="no">
											<label for="wizcdrno"><?php echo _("No");?></label>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="wizcdr-help" class="help-block fpbx-help-block"><?php echo _("Backup CDR and call record data?")?></span>
							</div>
						</div>
					</div>
					<!--END Backup CDR and Call Log Data-->
				</div>
				<div id="step-4">
					<h2 class="StepTitle"><?php echo _("Would you like notifications?")?></h2>
					<br/>
					<!--Email Notifications-->
					<div class="element-container">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="wiznotif"><?php echo _("Email Notifications") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="wiznotif"></i>
										</div>
										<div class="col-md-9 radioset">
											<input type="radio" name="wiznotif" id="wiznotifyes" value="yes" CHECKED>
											<label for="wiznotifyes"><?php echo _("Yes");?></label>
											<input type="radio" name="wiznotif" id="wiznotifno" value="no">
											<label for="wiznotifno"><?php echo _("No");?></label>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="wiznotif-help" class="help-block fpbx-help-block"><?php echo _("Would you like an email when this job runs?")?></span>
							</div>
						</div>
					</div>
					<!--END Email Notifications-->
					<!--Status Email-->
					<div class="element-container">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="wizemail"><?php echo _("Status Email") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="wizemail"></i>
										</div>
										<div class="col-md-9">
											<input type="text" class="form-control" id="wizemail" name="wizemail" value="">
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="wizemail-help" class="help-block fpbx-help-block"><?php echo _("Where should status emails for this job be sent?")?></span>
							</div>
						</div>
					</div>
					<!--END Status Email-->
				</div>
				<div id="step-5">
					<h2 class="StepTitle"><?php echo _("Remote save options")?></h2>
					<br/>
					<p><?php echo _("Your backup will be saved locally to /var/spool/asterisk/backup")?></p>
					<!--Backup Location-->
					<div class="element-container">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="wizremote"><?php echo _("Remote Save") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="wizremote"></i>
										</div>
										<div class="col-md-9 radioset">
											<input type="radio" name="wizremote" id="wizremoteyes" value="yes">
											<label for="wizremoteyes"><?php echo _("Yes");?></label>
											<input type="radio" name="wizremote" id="wizremoteno" value="no" CHECKED>
											<label for="wizremoteno"><?php echo _("No");?></label>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="wizremote-help" class="help-block fpbx-help-block"><?php echo _("Remote Server Settings?")?></span>
							</div>
						</div>
					</div>
					<!--END Backup Location-->
					<!--Remote Server Type-->
					<div class="element-container wizservboth hidden">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="wizremtype"><?php echo _("Remote Server Type") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="wizremtype"></i>
										</div>
										<div class="col-md-9 radioset">
											<input type="radio" name="wizremtype" id="wizremtypeftp" value="ftp" CHECKED>
											<label for="wizremtypeftp"><?php echo _("FTP");?></label>
											<input type="radio" name="wizremtype" id="wizremtypessh" value="ssh" >
											<label for="wizremtypessh"><?php echo _("SSH");?></label>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="wizremtime-help" class="help-block fpbx-help-block"><?php echo _("Remote Server type")?></span>
							</div>
						</div>
					</div>
					<!--END Remote Server Type-->
					<!--Server Name-->
					<div class="element-container wizservboth hidden">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="wizsevername"><?php echo _("Server Name") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="wizsevername"></i>
										</div>
										<div class="col-md-9">
											<input type="text" class="form-control" id="wizsevername" name="wizsevername" value="">
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="wizsevername-help" class="help-block fpbx-help-block"><?php echo _("Give the server a descriptive name")?></span>
							</div>
						</div>
					</div>
					<!--END Server Name-->
					<!--Server Address-->
					<div class="element-container wizservboth hidden">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="wizserveraddr"><?php echo _("Server Address") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="wizserveraddr"></i>
										</div>
										<div class="col-md-9">
											<input type="text" class="form-control" id="wizserveraddr" name="wizserveraddr" value="">
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="wizserveraddr-help" class="help-block fpbx-help-block"><?php echo _("IP Address or FQDN of your server")?></span>
							</div>
						</div>
					</div>
					<!--END Server Address-->
					<!--Server Port-->
					<div class="element-container wizservboth hidden">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="wizserverport"><?php echo _("Server Port") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="wizserverport"></i>
										</div>
										<div class="col-md-9">
											<input type="text" class="form-control" id="wizserverport" name="wizserverport" value="" placeholder=<?php echo _("Default")?>>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="wizserverport-help" class="help-block fpbx-help-block"><?php echo _("Server Port, leave blank for default")?></span>
							</div>
						</div>
					</div>
					<!--END Server Port-->
					<!--Username-->
					<div class="element-container wizservboth hidden">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="wizserveruser"><?php echo _("Username") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="wizserveruser"></i>
										</div>
										<div class="col-md-9">
											<input type="text" class="form-control" id="wizserveruser" name="wizserveruser" value="">
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="wizserveruser-help" class="help-block fpbx-help-block"><?php echo _("Server Username")?></span>
							</div>
						</div>
					</div>
					<!--END Username-->
					<!--Password-->
					<div class="element-container wizservftp hidden">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="wizftppass"><?php echo _("Password") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="wizftppass"></i>
										</div>
										<div class="col-md-9">
											<input type="password" class="form-control" id="wizftppass" name="wizftppass" value="">
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="wizftppass-help" class="help-block fpbx-help-block"><?php echo _("FTP Password")?></span>
							</div>
						</div>
					</div>
					<!--END Password-->
					<!--Transfer Type-->
					<div class="element-container wizservftp hidden">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="wiztrans"><?php echo _("Transfer Type") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="wiztrans"></i>
										</div>
										<div class="col-md-9 radioset">
											<input type="radio" name="wiztrans" id="wiztranspass" value="passive" CHECKED>
											<label for="wiztranspass"><?php echo _("Passive");?></label>
											<input type="radio" name="wiztrans" id="wiztransact" value="active">
											<label for="wiztransact"><?php echo _("Active");?></label>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="wiztrans-help" class="help-block fpbx-help-block"><?php echo _("FTP Transfer type")?></span>
							</div>
						</div>
					</div>
					<!--END Transfer Type-->
					<!--Remote Path-->
					<div class="element-container wizservboth hidden">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="wizremotepath"><?php echo _("Remote Path") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="wizremotepath"></i>
										</div>
										<div class="col-md-9">
											<input type="text" class="form-control" id="wizremotepath" name="wizremotepath" value="" placeholder="<?php echo _('Default')?>">
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="wizremotepath-help" class="help-block fpbx-help-block"><?php echo _("Remote save path, blank for default")?></span>
							</div>
						</div>
					</div>
					<!--END Remote Path-->
					<!--SSH Key Path-->
					<div class="element-container wizservssh hidden">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="wizsshkeypath"><?php echo _("SSH Key Path") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="wizsshkeypath"></i>
										</div>
										<div class="col-md-9">
											<input type="text" class="form-control" id="wizsshkeypath" name="wizsshkeypath" value="/home/asterisk/.ssh/id_rsa">
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="wizsshkeypath-help" class="help-block fpbx-help-block"><?php echo _("Path to ssh key, example /home/asterisk/.ssh/id_rsa")?></span>
							</div>
						</div>
					</div>
					<!--END SSH Key Path-->
				</div>
			</div>
		</div>
		</form>
	</div>
</div>
