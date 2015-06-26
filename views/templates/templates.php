<div id="toolbar-templates">
  <a class="btn btn-default" href="config.php?type=setup&amp;display=backup_templates&amp;action=edit"><?php echo _('New Template')?></a>
</div>
<table id="templatesGrid" data-toolbar="#toolbar-templates" data-pagination="true" data-search="true" data-url="ajax.php?module=backup&command=getJSON&jdata=templateGrid" data-cache="false" data-toggle="table" class="table table-striped">
  <thead>
    <tr>
      <th data-field="name" data-sortable="true"><?php echo _("Item")?></th>
      <th data-field="desc"><?php echo _("Description")?></th>
      <th data-field="id,immortal" data-formatter="templateFormatter"><?php echo _("Actions")?></th>
    </tr>
  </thead>
</table>
