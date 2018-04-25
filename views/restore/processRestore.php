<?php $date = \FreePBX::View()->getDateTime($meta['date']);?>
<div class="row">
  <div class = "col-md-5">
    <div class="panel panel-default">
      <div class="panel-heading"><h3><?php echo _("Backup Info")?></h3></div>
      <div class="panel-body">
        <ul class = "list-group">
          <li class="list-group-item"><b><?php echo _("Name")?></b><span class = "pull-right"><?php echo $meta['backupInfo']['backup_name']?><span></li>
          <li class="list-group-item"><b><?php echo _("Description")?></b><span class = "pull-right"><?php echo $meta['backupInfo']['backup_description']?><span></li>
          <li class="list-group-item"><b><?php echo _("Run Date")?></b><span class = "pull-right"><?php echo $date?><span></li>
        </ul>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="panel panel-default">
      <div class="panel-heading"><h3><?php echo _("Restore Information")?></h3></div>
      <div class="panel-body">
      <ul class="list-group">
        <li class = "list-group-item list-group-item-danger"><?php echo _("Running a restore will overwrite current data. This cannot be undone!")?></li>
        <li class = "list-group-item list-group-item-warning"><?php echo _("This restore will attempt to install and enable the module version in the backup.")?></li>
        <li class = "list-group-item list-group-item-info"><?php echo _("This restore will only affect the modules listed below")?></li>
        <li class = "list-group-item list-group-item-info"><?php echo _("After the restore you should reload with the apply config button")?></li>
      </ul>
    </div>
  </div> <!--End column-->
</div><!--End Row-->
</div>
<div id="restoremodule-toolbar">
  <h3><?php echo _("Modules in this backup")?></h3>
</div>
<table id="restoremodules"
  data-toggle="table"
  data-search="true"
  data-toolbar="#restoremodule-toolbar"
  data-id-field="modulename"
  data-maintain-selected="true"
  class="table table-striped">
  <thead>
    <tr>
      <th data-field="modulename" class="col-md-4"><?php echo _("Module")?></th>
      <th data-field="version" class="col-md-4"><?php echo _("Version in Backup")?></th>
      <th data-field="installed" class="col-md-4"><?php echo _("Status")?></th>
    </tr>
  </thead>
</table>
<script>
  $(document).ready(() => {
    $('#restoremodules').bootstrapTable({data: <?php echo $jsondata?>});
    window.fileid = "<?php echo $fileid?>";
  });//end ready
  
  function installedFormatter(v){
    if(v){
      return `<i class="fa fa-check text-success"></i>`;
    }else{
      return `<i class="fa fa-times text-danger"></i>`;
    }
  }
</script>
