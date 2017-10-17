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
      <div class="panel-heading"><h3><?php echo _("Restore Settings")?></h3></div>
      <div class="panel-body">
      <!--Install Missing-->
      <div class="element-container">
        <div class="row">
          <div class="form-group">
            <div class="col-md-3">
              <label class="control-label" for="missing"><?php echo _("Install Missing") ?></label>
              <i class="fa fa-question-circle fpbx-help-icon" data-for="missing"></i>
            </div>
            <div class="col-md-9 radioset">
              <input type="radio" name="missing" id="missingyes" value="yes" <?php echo ($missing == "yes"?"CHECKED":"") ?>>
              <label for="missingyes"><?php echo _("Yes");?></label>
              <input type="radio" name="missing" id="missingno" value="no" <?php echo ($missing == "no"?"CHECKED":"") ?>>
              <label for="missingno"><?php echo _("No");?></label>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-12">
            <span id="missing-help" class="help-block fpbx-help-block"><?php echo _("Should we automatically install modules not currently installed?")?></span>
          </div>
        </div>
      </div>
      <!--END Install Missing-->
      <!--Reset Modules-->
      <div class="element-container">
        <div class="row">
          <div class="form-group">
            <div class="col-md-3">
              <label class="control-label" for="reset"><?php echo _("Reset Modules") ?></label>
              <i class="fa fa-question-circle fpbx-help-icon" data-for="reset"></i>
            </div>
            <div class="col-md-9 radioset">
              <input type="radio" name="reset" id="resetyes" value="yes" <?php echo ($reset == "yes"?"CHECKED":"") ?>>
              <label for="resetyes"><?php echo _("Yes");?></label>
              <input type="radio" name="reset" id="resetno" value="no" <?php echo ($reset == "no"?"CHECKED":"") ?>>
              <label for="resetno"><?php echo _("No");?></label>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-12">
            <span id="reset-help" class="help-block fpbx-help-block"><?php echo _("If set the modules will be asked to remove existing data")?></span>
          </div>
        </div>
      </div>
      <!--END Reset Modules-->

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
      <th data-field="modulename" class="col-md-10"><?php echo _("Module")?></th>
      <th data-field="installed" data-formatter="installedFormatter" class="col-md-2"><?php echo _("Installed")?></th>
    </tr>
  </thead>
</table>
<script>
  $(document).ready(() => {
    $('#restoremodules').bootstrapTable({data: <?php echo $jsondata?>});
  });
  function installedFormatter(v){
    if(v){
      return `<i class="fa fa-check text-success"></i>`;
    }else{
      return `<i class="fa fa-times text-danger"></i>`;
    }
  }
</script>
