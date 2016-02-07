<?php
$disabled = (isset($readonly) && !empty($readonly))?' disabled ':'';
?>
<h2><?php echo _("SSH Server")?></h2>
<form class="fpbx-submit" action="" method="post" id="server_form" name="server_form">
	<input type="hidden" name="action" value="save">
	<input type="hidden" name="id" value="<?php echo isset($id)?$id:''?>">
	<input type="hidden" name="server_type" value="ssh">
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
				<span id="name-help" class="help-block fpbx-help-block"><?php echo _("Provide the name for this server")?></span>
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
							<input type="text" class="form-control" id="host" name="host" value="<?php echo isset($host)?$host:''?>"<?php echo $disabled?>>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="host-help" class="help-block fpbx-help-block"><?php echo _("IP address or FQDN of remote SSH server")?></span>
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
				<span id="port-help" class="help-block fpbx-help-block"><?php echo _("Remote SSH Port")?></span>
			</div>
		</div>
	</div>
	<!--END Port-->
	<!--Username-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="user"><?php echo _("Username") ?></label>
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
				<span id="user-help" class="help-block fpbx-help-block"><?php echo _("SSH Username")?></span>
			</div>
		</div>
	</div>
	<!--END Username-->
	<!--Key-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="key"><?php echo _("Key") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="key"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="key" name="key" value="<?php echo isset($key)?$key:''?>"<?php echo $disabled?>>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="key-help" class="help-block fpbx-help-block"><?php echo _("Location of ssh private key to be used when connecting to a host")?></span>
			</div>
		</div>
	</div>
	<!--END Key-->
	<!--Path-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="path"><?php echo _("Path") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="path"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="path" name="path" value="<?php echo isset($path)?$path:''?>"<?php echo $disabled?>>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="path-help" class="help-block fpbx-help-block"><?php echo _("Path on remote server")?></span>
			</div>
		</div>
	</div>
	<!--END Path-->
</form>
<script type="text/javascript">
  var immortal = <?php echo (isset($immortal) && !empty($immortal))?'true':'false';?>;
</script>
