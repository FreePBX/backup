<?php
if(isset($error) && !empty($error)){
	echo '<div class = "alert alert-danger">'.$error.'</div>';
}
?>
<div class="panel panel-primary">
	<div class="panel-heading">
		<h3 class="panel-title"><?php echo _("Upload your restore files")?></h3>
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
						<div id="uploadrestore" class="alert alert-info">
							<i class="fa fa-upload"></i> <?php echo _('Click to upload a backup file.')?>
						</div>
						<div class="progress">
  							<div class="progress-bar progress-bar-striped" role="progressbar" aria-valuemin="0" data-last="0" aria-valuemax="100" id="uploadprogress" style="min-width: 3em;">
    							0.00%
  							</div>
						</div>

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
		<div id="toolbar-localrestorefiles">
			<button id="remove-localrestorefiles" class="btn btn-danger btn-remove" data-type="localrestorefiles" disabled>
				<i class="fa fa-user-times"></i> <span><?php echo _('Delete')?></span>
			</button>
		</div>
		<table id="localrestorefiles"
			data-type="localrestorefiles"
			data-toolbar="#toolbar-localrestorefiles"
			data-url="ajax.php?module=backup&command=localRestoreFiles"
			data-cache="false"
			data-maintain-selected="true"
			data-show-columns="true"
			data-show-toggle="true"
			data-toggle="table"
			data-sort-order="desc"
			data-sort-name="timestamp"
			data-pagination="true"
			data-search="true"
			data-unique-id="id"
			data-escape="true" 
			class="table table-striped">
			<thead>
				<tr>
					<th data-checkbox="true"></th>
					<th data-field="name"><?php echo _("Backup Name")?></th>
					<th data-field="timestamp" data-formatter="timestampFormatter"><?php echo _("Backup Date")?></th>
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
		<div id="toolbar-restoreFiles">
			<button id="remove-restoreFiles" class="btn btn-danger btn-remove" data-type="restoreFiles" disabled>
				<i class="fa fa-user-times"></i> <span><?php echo _('Delete')?></span>
			</button>
		</div>
		<table id="restoreFiles"
			data-type="restoreFiles"
			data-toolbar="#toolbar-restoreFiles"
			data-url="<?php echo $dataurl?>"
			data-cache="false"
			data-maintain-selected="true"
			data-show-columns="true"
			data-show-toggle="true"
			data-sort-order="desc"
			data-sort-name="timestamp"
			data-toggle="table"
			data-pagination="true"
			data-search="true"
			data-unique-id="id" 
			data-escape="true" 
			class="table table-striped">
			<thead>
				<tr>
					<th data-checkbox="true"></th>
					<th data-field="name"><?php echo _("Backup Name")?></th>
					<th data-field="timestamp" data-formatter="timestampFormatter"><?php echo _("Backup Date")?></th>
					<th data-field="type"><?php echo _("Backup Type")?></th>
					<th data-field="instancename"><?php echo _("Backup Instance")?></th>
					<th data-field="id" data-formatter="remoteFormatter"><?php echo _("Actions")?></th>
				</tr>
			</thead>
		</table>
	</div>
</div>
<script>var runningRestore = <?php echo json_encode($runningRestore); ?>;</script>
