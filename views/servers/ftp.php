<?php
$disabled = (isset($readonly) && !empty($readonly))?' disabled ':'';
if (!isset($id)) {
	$id = "";
}

?>
<h2><?php echo _("FTP Server")?></h2>
<form class="fpbx-submit" action="" method="post" id="server_form" name="server_form" data-fpbx-delete="?display=backup_servers&action=delete&id=<?php echo $id; ?>">
	<input type="hidden" name="action" value="save">
	<input type="hidden" name="id" value="<?php echo $id?>">
	<input type="hidden" name="server_type" value="ftp">
	<!--Server Name-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="name"><?php echo _("Server Name") ?></label>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="name" name="name" value="<?php echo isset($name)?$name:''?>"<?php echo $disabled?>>
						</div>
					</div>
				</div>
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
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="desc" name="desc" value="<?php echo isset($desc)?$desc:''?>"<?php echo $disabled?>>
						</div>
					</div>
				</div>
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
				<span id="host-help" class="help-block fpbx-help-block"><?php echo _("IP address or FQDN of remote ftp host")?></span>
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
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="port" name="port" value="<?php echo isset($port)?$port:''?>"<?php echo $disabled?>>
						</div>
					</div>
				</div>
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
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="user" name="user" value="<?php echo isset($user)?$user:''?>"<?php echo $disabled?>>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<!--END Username-->
	<!--Password-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="password"><?php echo _("Password") ?></label>
						</div>
						<div class="col-md-9">
							<input type="password" class="form-control" id="password" name="password" value="<?php echo isset($password)?$password:''?>"<?php echo $disabled?>>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<!--END Password-->
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
	<!--Transfer Mode-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="transfer"><?php echo _("Transfer Mode") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="transfer"></i>
						</div>
						<div class="col-md-9 radioset">
	            <input type="radio" name="transfer" id="transferactive" value="active" <?php echo ($transfer == "active"?"CHECKED":"") ?><?php echo $disabled?>>
	            <label for="transferactive"><?php echo _("Active");?></label>
	            <input type="radio" name="transfer" id="transferpassive" value="passive" <?php echo ($transfer == "active"?"":"CHECKED") ?><?php echo $disabled?>>
	            <label for="transferpassive"><?php echo _("Passive");?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="transfer-help" class="help-block fpbx-help-block"><?php echo _("This defaults to 'Passive'. If your FTP server is behind a seperate NAT or Firewall to this VoIP server, you should select 'Active'. In 'Active' mode, the FTP server establishes a connection back to the VoIP server to receive the data. In 'Passive' mode, the VoIP server connects to the FTP Server to send data.")?></span>
			</div>
		</div>
	</div>
	<!--END Transfer Mode-->
</form>

<script type="text/javascript">
  var immortal = <?php echo (isset($immortal) && !empty($immortal))?'true':'false';?>;
</script>
