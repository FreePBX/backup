
<?php
$dataurl = "ajax.php?module=backup&command=getJSON&jdata=serverGrid";
?>
<ul class="nav nav-tabs">
  <li class="active"><a data-toggle="tab" href="#import"><?php echo _("Import")?></a></li>
  <li><a data-toggle="tab" href="#browse"><?php echo _("Browse")?></a></li>
</ul>
<div class="tab-content">
  <div id="import" class="tab-pane fade in active">
    <form class="fpbx-submit" id="restore_form" action="" method="post" enctype="multipart/form-data">
      <!--Upload File-->
      <div class="element-container">
        <div class="row">
          <div class="col-md-12">
            <div class="row">
              <div class="form-group">
                <div class="col-md-3">
                  <label class="control-label" for="upload"><?php echo _("Upload File") ?></label>
                  <i class="fa fa-question-circle fpbx-help-icon" data-for="upload"></i>
                </div>
                <div class="col-md-9">
                  <span class="btn btn-default btn-file">
                    <?php echo _("Browse")?> <input type="file" class="form-control" name="upload" id="upload">
                  </span>
                  <span class="filename"></span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-12">
            <span id="upload-help" class="help-block fpbx-help-block"><?php echo _("")?></span>
          </div>
        </div>
      </div>
      <!--END Upload File-->
      <input type="hidden" name="action" value="upload">
      <input type="submit" name="submit" id="submit" class="pull-right" value="<?php echo _("Restore")?>">
    </form>
  </div>
  <div id="browse" class="tab-pane fade">
    <div id="toolbar-all">
        <button id="remove-all" class="btn btn-danger btn-remove" data-type="extensions" disabled data-section="all">
            <i class="glyphicon glyphicon-remove"></i> <span><?php echo _('Delete')?></span>
        </button>
    </div>
     <table id="servers"
            data-url="<?php echo $dataurl?>"
            data-cache="false"
            data-cookie="true"
            data-cookie-id-table="<must be a uniquely global name throughout all of freepbx>"
            data-toolbar="#toolbar-all"
            data-maintain-selected="true"
            data-show-columns="true"
            data-show-toggle="true"
            data-toggle="table"
            data-pagination="true"
            data-search="true"
            class="table table-striped">
        <thead>
            <tr>
                <th data-field="name" data-formatter="serverformatter"><?php echo _("Server")?></th>
            </tr>
        </thead>
    </table>
  </div>
</div>
<script type="text/javascript">
function serverformatter(v,r){
  return '<a href = "?display=backup_restore&id='+r['id']+'">'+v+'</a>';
}
</script>
