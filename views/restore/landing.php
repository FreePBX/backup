<?php
if(isset($error) && !empty($error)){
	echo '<div class = "alert alert-danger">'.$error.'</div>';
}
?>
<div class="panel panel-primary">
	<div class="panel-heading">
		<h3 class="panel-title"><?php echo _("Upload your restore file")?></h3>
	</div>
	<div class="panel-body">
		<!--Backup File-->
		<div class="element-container">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="filetorestore"><?php echo _("Backup File") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="filetorestore"></i>
					</div>
					<div class="col-md-9">
						<input type="file" data-max-files="1" id="filetorestore" name="filetorestore" value="">
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="filetorestore-help" class="help-block fpbx-help-block"><?php echo _("Upload a valid backup.tar.gz")?></span>
				</div>
			</div>
		</div>
		<!--END Backup File-->
	</div>
</div>

<div class="panel panel-primary">
	<div class="panel-heading">
		<h3 class="panel-title"><?php echo _("Restore from local cache")?></h3>
	</div>
	<div class="panel-body">
		<?php
			$localdataurl = "ajax.php?module=backup&command=localRestoreFiles";
		?>
		<table id="localrestorefiles"
			data-url="<?php echo $localdataurl?>"
			data-cache="false"
			data-maintain-selected="true"
			data-show-columns="true"
			data-show-toggle="true"
			data-toggle="table"
			data-pagination="true"
			data-search="true"
			class="table table-striped">
			<thead>
				<tr>
					<th data-field="name"><?php echo _("Backup Name")?></th>
					<th data-field="date"><?php echo _("Backup Date")?></th>
					<th data-field="framework"><?php echo _("Framework")?></th>
					<th data-field="id" data-formatter="localLinkFormatter"><?php echo _("Actions")?></th>
				</tr>
			</thead>
		</table>
	</div>
</div>
<div class="panel panel-primary">
	<div class="panel-heading">
		<h3 class="panel-title"><?php echo _("Restore from the cloud")?></h3>
	</div>
	<div class="panel-body">
		<div class="well well-info">
			<?php echo _("This feature requires filestore be setup and may not be availible on a clean install")?>
		</div>
		<?php
			$dataurl = "ajax.php?module=backup&command=restoreFiles";
		?>

		<table id="restoreFiles"
			data-url="<?php echo $dataurl?>"
			data-cache="false"
			data-maintain-selected="true"
			data-show-columns="true"
			data-show-toggle="true"
			data-toggle="table"
			data-pagination="true"
			data-search="true"
			class="table table-striped">
			<thead>
				<tr>
					<th data-field="name"><?php echo _("Backup Name")?></th>
					<th data-field="date"><?php echo _("Backup Date")?></th>
					<th data-field="type"><?php echo _("Backup Type")?></th>
					<th data-field="id" data-formatter="remoteFormatter"><?php echo _("Actions")?></th>
				</tr>
			</thead>
		</table>
	</div>
</div>
