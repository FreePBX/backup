<?php
$disabled = (isset($readonly) && !empty($readonly))?' disabled ':'';
?>
<h2><?php echo _("MySQL Server")?></h2>
<form class="fpbx-submit" action="" method="post" id="server_form" name="server_form">
	<input type="hidden" name="action" value="save">
	<input type="hidden" name="id" value="<?php echo isset($id)?$id:''?>">
	<input type="hidden" name="server_type" value="mysql">
	<!--Server Name-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="name"><?php echo _("Server Name") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="name"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="name" name="name" value="<?php echo isset($name)?$name:''?>"<?php echo $disabled?>>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="name-help" class="help-block fpbx-help-block"><?php echo _("Supply a server name")?></span>
			</div>
		</div>
	</div>
	<!--END Server Name-->
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
							<input type="text" class="form-control" id="desc" name="desc" value="<?php echo isset($desc)?$desc:''?>"<?php echo $disabled?>>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="desc-help" class="help-block fpbx-help-block"><?php echo _("Description or notes for this server")?></span>
			</div>
		</div>
	</div>
	<!--END Description-->
	<!--Hostname-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="host"><?php echo _("Hostname") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="host"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="host" name="host" value="<?php echo isset($host)?$host:''?>" <?php echo $disabled?>>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="host-help" class="help-block fpbx-help-block"><?php echo _("IP address or FQDN of remote mysql host")?></span>
			</div>
		</div>
	</div>
	<!--END Hostname-->
	<!--Port-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="port"><?php echo _("Port") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="port"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="port" name="port" value="<?php echo isset($port)?$port:''?>"<?php echo $disabled?>>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="port-help" class="help-block fpbx-help-block"><?php echo _("Remote MySQL Port")?></span>
			</div>
		</div>
	</div>
	<!--END Port-->
	<!--User Name-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="user"><?php echo _("User Name") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="user"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="user" name="user" value="<?php echo isset($user)?$user:''?>"<?php echo $disabled?>>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="user-help" class="help-block fpbx-help-block"><?php echo _("FTP Username")?></span>
			</div>
		</div>
	</div>
	<!--END User Name-->
	<!--Password-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="password"><?php echo _("Password") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="password"></i>
						</div>
						<div class="col-md-9">
							<input type="password" class="form-control clicktoedit" id="password" name="password" value="<?php echo isset($password)?$password:''?>"<?php echo $disabled?>>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="password-help" class="help-block fpbx-help-block"><?php echo _("FTP Password")?></span>
			</div>
		</div>
	</div>
	<!--END Password-->
	<!--DB Name-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="dbname"><?php echo _("DB Name") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="dbname"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="dbname" name="dbname" value="<?php echo isset($dbname)?$dbname:''?>"<?php echo $disabled?>>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="dbname-help" class="help-block fpbx-help-block"><?php echo _("Remote Database Name")?></span>
			</div>
		</div>
	</div>
	<!--END DB Name-->
</form>

<script type="text/javascript">
  var immortal = <?php echo (isset($immortal) && !empty($immortal))?'true':'false';?>;
</script>
