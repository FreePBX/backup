<form
  class="fpbx-submit"
  name="frm_extensions"
  action="?display=backup"
  method="post"
  data-fpbx-delete="config.php?display=backup&type=backup&amp;id=<?php echo $id?>&amp;action=del" role="form"
>
  <input type="hidden" id="id" name="id" value="<?php echo $id ?>">
  <div class="section-title" data-for="backup-basic"><h3><i class="fa fa-minus"></i> <?php echo _("Basic Information") ?></h3></div>
  <div class="section" data-id="backup-basic">
    <!--Backup Name-->
    <div class="element-container">
      <div class="row">
        <div class="form-group">
          <div class="col-md-3">
            <label class="control-label" for="backup_name"><?php echo _("Backup Name") ?></label>
            <i class="fa fa-question-circle fpbx-help-icon" data-for="backup_name"></i>
          </div>
          <div class="col-md-9">
            <input type="text" class="form-control" id="backup_name" name="backup_name" value="<?php echo isset($backup_name)?$backup_name:''?>">
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-md-12">
          <span id="backup_name-help" class="help-block fpbx-help-block"><?php echo _("A name used to identify your backups")?></span>
        </div>
      </div>
    </div>
    <!--END Backup Name-->
    <!--Backup Description-->
    <div class="element-container">
      <div class="row">
        <div class="form-group">
          <div class="col-md-3">
            <label class="control-label" for="backup_description"><?php echo _("Backup Description") ?></label>
            <i class="fa fa-question-circle fpbx-help-icon" data-for="backup_description"></i>
          </div>
          <div class="col-md-9">
            <input type="text" class="form-control" id="backup_description" name="backup_description" value="<?php echo isset($backup_description)?$backup_description:''?>">
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-md-12">
          <span id="backup_description-help" class="help-block fpbx-help-block"><?php echo _("Add a description for your backup")?></span>
        </div>
      </div>
    </div>
    <!--END Backup Description-->
    <!--Backup Items-->
    <div class="element-container">
      <div class="row">
        <div class="form-group">
          <div class="col-md-3">
            <label class="control-label" for="backup_items"><?php echo _("Backup Items") ?></label>
            <i class="fa fa-question-circle fpbx-help-icon" data-for="backup_items"></i>
          </div>
          <div class="col-md-9">
            <select class="form-control" id="backup_items" name="backup_items[]" multiple="multiple">
            </select>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-md-12">
          <span id="backup_items-help" class="help-block fpbx-help-block"><?php echo _("Select items to backup, create new items in the Filestore module")?></span>
        </div>
      </div>
    </div>
    <!--END Backup Items-->
  </div>
  <div class="section-title" data-for="backup-storage"><h3><i class="fa fa-minus"></i> <?php echo _("Storage") ?></h3></div>
  <div class="section" data-id="backup-storage">
    <!--Storage Location-->
    <div class="element-container">
      <div class="row">
        <div class="form-group">
          <div class="col-md-3">
            <label class="control-label" for="backup_storage"><?php echo _("Storage Location") ?></label>
            <i class="fa fa-question-circle fpbx-help-icon" data-for="backup_storage"></i>
          </div>
          <div class="col-md-9">
            <select class="form-control" id="backup_storage" name="backup_storage[]" multiple="multiple">
              <?php echo $storageopts ?>
            </select>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-md-12">
          <span id="backup_storage-help" class="help-block fpbx-help-block"><?php echo _("Select one or more storage locations")?></span>
        </div>
      </div>
    </div>
    <!--END Storage Location-->
  </div>
  <div class="section-title" data-for="backup-schedule"><h3><i class="fa fa-minus"></i> <?php echo _("Schedule and Maintinence") ?></h3></div>
  <div class="section" data-id="backup-schedule">
    <!--Scheduleing-->
    <div class="element-container">
      <div class="row">
        <div class="form-group">
          <div class="col-md-3">
            <label class="control-label" for="backup_schedule"><?php echo _("Scheduleing") ?></label>
            <i class="fa fa-question-circle fpbx-help-icon" data-for="backup_schedule"></i>
          </div>
          <div class="col-md-9">
            <input type="text" class="form-control" id="backup_schedule" name="backup_schedule" value="<?php echo isset($backup_schedule)?$backup_schedule:''?>">
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-md-12">
          <span id="backup_schedule-help" class="help-block fpbx-help-block"><?php echo _("When should this backup run")?></span>
        </div>
      </div>
    </div>
    <!--END Scheduleing-->
    <!--Maintinance-->
    <div class="element-container">
      <div class="row">
        <div class="form-group">
          <div class="col-md-3">
            <label class="control-label" for="backup_maintinance"><?php echo _("Maintinance") ?></label>
            <i class="fa fa-question-circle fpbx-help-icon" data-for="backup_maintinance"></i>
          </div>
          <div class="col-md-9">
            <input type="text" class="form-control" id="backup_maintinance" name="backup_maintinance" value="<?php echo isset($backup_maintinance)?$backup_maintinance:''?>">
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-md-12">
          <span id="backup_maintinance-help" class="help-block fpbx-help-block"><?php echo _("HowMany/How often")?></span>
        </div>
      </div>
    </div>
    <!--END Maintinance-->
  </div>
</form>
