<div id="toolbar-servers">
  <div class="btn-group">
    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
      <?php echo _("Add Server")?><span class="caret"></span>
    </button>
    <ul class="dropdown-menu" role="menu">
      <li><a href="config.php?type=setup&amp;display=backup_servers&amp;action=edit&amp;server_type=ftp"><i class="fa fa-plus"></i> <?php echo _('New FTP server')?></a></li>
      <li><a href="config.php?type=setup&amp;display=backup_servers&amp;action=edit&amp;server_type=ssh"><i class="fa fa-plus"></i> <?php echo _('New SSH server')?></a></li>
      <li><a href="config.php?type=setup&amp;display=backup_servers&amp;action=edit&amp;server_type=email"><i class="fa fa-plus"></i> <?php echo _('New Email server')?></a></li>
      <li><a href="config.php?type=setup&amp;display=backup_servers&amp;action=edit&amp;server_type=local"><i class="fa fa-plus"></i> <?php echo _('New Local server')?></a></li>
      <li><a href="config.php?type=setup&amp;display=backup_servers&amp;action=edit&amp;server_type=mysql"><i class="fa fa-plus"></i> <?php echo _('New MySQL server')?></a></li>
      <li><a href="config.php?type=setup&amp;display=backup_servers&amp;action=edit&amp;server_type=awss3"><i class="fa fa-plus"></i> <?php echo _('New Amazon S3 server')?></a></li>
    </ul>
  </div>
</div>
<table id="serversGridall" data-toolbar="#toolbar-servers" data-pagination="true" data-search="true" data-url="ajax.php?module=backup&amp;command=getJSON&amp;jdata=serverGrid" data-cache="false" data-toggle="table" class="table table-striped">
  <thead>
    <tr>
      <th data-field="name" data-sortable="true"><?php echo _("Item")?></th>
      <th data-field="desc"><?php echo _("Description")?></th>
      <th data-field="type" data-sortable="true"><?php echo _("Type")?></th>
      <th data-field="id,immortal" data-formatter="serverFormatter"><?php echo _("Actions")?></th>
    </tr>
  </thead>
</table>
