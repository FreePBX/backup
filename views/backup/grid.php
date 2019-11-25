<?php
  $dataurl = "ajax.php?module=backup&command=backupGrid";
?>
<div id="toolbar-backup">
  <a href='?display=backup&view=addbackup' class="btn btn-default"><i class = "fa fa-plus"></i>&nbsp;<?php echo _("Add Backup")?></a>
  <!--<a href='' class="btn btn-default disabled" id="wizard"><i class = "fa fa-magic"></i>&nbsp;<?php echo _("Backup Wizard")?></a>-->
</div>
 <table id="backup_backup"
        data-url="<?php echo $dataurl?>"
        data-cache="false"
        data-cookie="true"
        data-cookie-id-table="backup_backup"
        data-toolbar="#toolbar-backup"
        data-maintain-selected="true"
        data-show-columns="true"
        data-show-toggle="true"
        data-toggle="table"
        data-pagination="true"
        data-search="true"
        data-escape="true" 
        class="table table-striped">
    <thead>
        <tr>
            <th data-field="name"><?php echo _("Name")?></th>
            <th data-field="description"><?php echo _("Description")?></th>
            <th data-field="id" data-formatter="linkFormatter"><?php echo _("Actions")?></th>
        </tr>
    </thead>
</table>
<script>var runningBackupJobs = <?php echo json_encode($runningBackups)?>;</script>