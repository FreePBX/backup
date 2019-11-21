<div id="toolbar-all">
	<a href="?display=backup" class="btn btn-default"><i class="fa fa-list"></i> <?php echo _("List Backups")?></a>
	<a href="?display=backup&view=addbackup" class="btn btn-default"><i class="fa fa-plus"></i> <?php echo _("Add Backup")?></a>
</div>
<table id="backup-side" data-url="ajax.php?module=backup&command=backupGrid" data-escape="true" data-cache="false" data-toolbar="#toolbar-all" data-toggle="table" data-search="true" class="table">
	<thead>
		<tr>
			<th data-field="name"><?php echo _("Name")?></th>
			<th data-field="description"><?php echo _("Description")?></th>
		</tr>
	</thead>
</table>
