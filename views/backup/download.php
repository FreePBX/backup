<table id="restorefiles"
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
      <th data-field="backuptype"><?php echo _("Backup Type")?></th>
      <th data-field="link" data-formatter="linkFormatter"><?php echo _("Actions")?></th>
    </tr>
  </thead>
</table>
