<?php
$data = array(
			'never'		=> _('Never'),
			'hourly'	=> _('Hourly'),
			'daily'		=> _('Daily'),
			'weekly'	=> _('Weekly'),
			'monthly'	=> _('Monthly'),
			'annually'	=> _('Annually'),
			'reboot'	=> _('Reboot'),
			'custom'	=> _('Custom')
);
$runopts = '';
foreach ($data as $key => $value) {
	$runopts .= '<option value="'.$key.'" '.($key == $cron_schedule?'SELECTED':'').'>'.$value.'</option>';
}
$cron_minute_opts ='';
for($i = 0; $i < 60; $i++) {
	$checked = in_array($i, $cron_minute) ? 'SELECTED' : '';
	$cron_minute_opts .= '<option value='.$i.' '.$checked.'>'.sprintf("%02d", $i).'</option>';
}
$cron_hour_opts='';
for($i = 0; $i < 24; $i++) {
	$checked =  in_array($i, $cron_hour) ? 'SELECTED' : '';
	$cron_hour_opts .= '<option value='.$i.' '.$checked.'>'.sprintf("%02d", $i).'</option>';
}
$doy = array(
		'0' => _('Sunday'),
		'1' => _('Monday'),
		'2' => _('Tuesday'),
		'3' => _('Wednesday'),
		'4' => _('Thursday'),
		'5' => _('Friday'),
		'6' => _('Saturday'),
);
$cron_dow_opts='';
foreach ($doy as $k => $v) {
	$checked = in_array($k, $cron_dow) ? 'SELECTED' : '';
	$cron_dow_opts .= '<option value='.$k.' '.$checked.'>'.$v.'</option>';
}

//month
$moy = array(
		'1' => _('January'),
		'2' => _('February'),
		'3' => _('March'),
		'4' => _('April'),
		'5' => _('May'),
		'6' => _('June'),
		'7' => _('July'),
		'8' => _('August'),
		'9' => _('September'),
		'10' => _('October'),
		'11' => _('November'),
		'12' => _('December'),
);
$cron_month_opts='';
foreach ($moy as $k => $v) {
	$checked = in_array($k, $cron_month) ? 'SELECTED' : '';
	$cron_month_opts .= '<option value='.$k.' '.$checked.'>'.$v.'</option>';
}
//day of month
$cron_dom_opts='';
for($i = 1; $i < 32; $i++) {
	$checked = in_array($i, $cron_dom) ? 'SELECTED' : '';
	$cron_dom_opts .= '<option value='.$i.' '.$checked.'>'.sprintf("%02d", $i).'</option>';
}


?>
<div class="panel panel-default" id="cron_help">
	<div class="panel-heading"><h3><a href="#cronhelptxt" data-toggle="collapse" data-target="#cronhelptxt"> <?php echo _("Schedule Help")?></a></h3></div>
	<div class="panel-body collapse" id="cronhelptxt">
			<?php echo _("Select how often to run this backup. The following schedule will be followed for all but custom.")?>
			<br/>
			<div class="section-title" data-for="bucronh"><b><i class="fa fa-minus"></i> <?php echo ("Hourly")?></b></div>
				<div class="section" data-id="bucronh">
    			<?php echo _("Run once an hour, beginning of hour")?>
				</div>
			<div class="section-title" data-for="bucrond"><b><i class="fa fa-minus"></i> <?php echo ("Daily")?></b></div>
				<div class="section" data-id="bucrond">
    			<?php echo _("Run once a day, at midnight")?>
				</div>
			<div class="section-title" data-for="bucronw"><b><i class="fa fa-minus"></i> <?php echo ("Weekly")?></b></div>
				<div class="section" data-id="bucronw">
    			<?php echo _("Run once a week, midnight on Sun")?>
				</div>
			<div class="section-title" data-for="bucronm"><b><i class="fa fa-minus"></i> <?php echo ("Monthly")?></b></div>
				<div class="section" data-id="bucronm">
    			<?php echo _("Run once a month, midnight, first of month")?>
				</div>
			<div class="section-title" data-for="bucrona"><b><i class="fa fa-minus"></i> <?php echo ("Anually")?></b></div>
				<div class="section" data-id="bucrona">
    			<?php echo _("Run once a year, midnight, Jan. 1")?>
				</div>
			<div class="section-title" data-for="bucronr"><b><i class="fa fa-minus"></i> <?php echo ("Reboot")?></b></div>
				<div class="section" data-id="bucronr">
    			<?php echo _("Run at startup of the server OR of the cron deamon (i.e. after every <code>service cron restart</code>)")?>
				</div>
			<div class="section-title" data-for="bucronn"><b><i class="fa fa-minus"></i> <?php echo ("Never")?></b></div>
				<div class="section" data-id="bucronn">
    			<?php echo _("Never will never run the backup automatically")?>
				</div>
			<div class="section-title" data-for="bucronc"><b><i class="fa fa-minus"></i> <?php echo ("Custom")?></b></div>
				<div class="section" data-id="bucronc">
			<?php echo _("If a custom schedule is selected, any section not specified will be considered to be \"any\" (aka: wildcard).
					I.e. if Day of Month is set to 12 and Day of Week is not set, the Backup will be run on ANY 12th of
					the month - regardless of the day of the week. If Day of Week is set to, say, Monday, the Backup will run ONLY
					 on a Monday, and ONLY if it's the 12th of the month.")?>
				</div>
	</div>
</div>

<!--Run Automatically-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="cron_schedule"><?php echo _("Run Automatically") ?></label>
					</div>
					<div class="col-md-9">
						<select class="form-control" id="cron_schedule" name="cron_schedule">
								<?php echo $runopts?>
						</select>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<!--END Run Automatically-->
<!--Random-->
<div class="element-container" id="randominput">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="cron_random"><?php echo _("Randomize") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="cron_random"></i>
					</div>
					<div class="col-md-9 radioset">
            <input type="radio" name="cron_random" id="cron_randomyes" value="true" <?php echo ($cron_random == "true"?"CHECKED":"") ?>>
            <label for="cron_randomyes"><?php echo _("Yes");?></label>
            <input type="radio" name="cron_random" id="cron_randomno" <?php echo ($cron_random == "true"?"":"CHECKED") ?>>
            <label for="cron_randomno"><?php echo _("No");?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="cron_random-help" class="help-block fpbx-help-block"><?php echo _("If Randomize is selected, a similar frequency will be followed, only the exact times will be randomized (avoiding peak business hours, when possible). Please note: randomized schedules will be rescheduled (randomly) every time ANY backup is saved")?></span>
		</div>
	</div>
</div>
<!--END Random-->
<div id="crondiv">
<!--Minutes-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="cron_minute"><?php echo _("Minutes") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="cron_minute"></i>
					</div>
					<div class="col-md-9">
						<select id="cron_minute" name="cron_minute[]" data-placeholder="<?php echo _("Minutes")?>" multiple class="form-control chosen chosen-select">
							<?php echo $cron_minute_opts ?>
						</select>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="cron_minute-help" class="help-block fpbx-help-block"><?php echo _("Which minutes to run")?></span>
		</div>
	</div>
</div>
<!--END Minutes-->
<!--Hours-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="cron_hour"><?php echo _("Hours") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="cron_hour"></i>
					</div>
					<div class="col-md-9">
						<select id="cron_hour" name="cron_hour[]" data-placeholder="<?php echo _("Hours")?>" multiple class="form-control chosen chosen-select">
							<?php echo $cron_hour_opts ?>
						</select>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="cron_hour-help" class="help-block fpbx-help-block"><?php echo _("Which Hours to run.")?></span>
		</div>
	</div>
</div>
<!--END Hours-->
<!--Week Days-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="cron_dow"><?php echo _("Week Days") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="cron_dow"></i>
					</div>
					<div class="col-md-9">
						<select id="cron_dow" name="cron_dow[]" data-placeholder="<?php echo _("Day of Week")?>" multiple class="form-control chosen chosen-select">
							<?php echo $cron_dow_opts ?>
						</select>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="cron_dow-help" class="help-block fpbx-help-block"><?php echo _("Which Days of the week to run")?></span>
		</div>
	</div>
</div>
<!--END Week Days-->
<!--Months-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="cron_month"><?php echo _("Months") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="cron_month"></i>
					</div>
					<div class="col-md-9">
						<select id="cron_month" name="cron_month[]" data-placeholder="<?php echo _("Months")?>" multiple class="form-control chosen chosen-select">
							<?php echo $cron_month_opts ?>
						</select>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="cron_month-help" class="help-block fpbx-help-block"><?php echo _("Which Months to run")?></span>
		</div>
	</div>
</div>
<!--END Months-->
<!--Days of Month-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="cron_dom"><?php echo _("Days of Month") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="cron_dom"></i>
					</div>
					<div class="col-md-9">
						<select id="cron_dom" name="cron_dom[]" data-placeholder="<?php echo _("Days of Month")?>" multiple class="form-control chosen chosen-select">
							<?php echo $cron_dom_opts ?>
						</select>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="cron_dom-help" class="help-block fpbx-help-block"><?php echo _("Days of month to run")?></span>
		</div>
	</div>
</div>
<!--END Days of Month-->
</div>
