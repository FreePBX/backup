<form class="fpbx-submit" id="restore_form" action="" method="post" enctype="multipart/form-data">
  <input type="hidden" name="action" value="upload">
  <span class="btn btn-default btn-file">
    <?php echo _("Browse")?> <input type="file" class="form-control" name="upload" id="upload">
  </span>
  <span class="filename"></span>
  <br/>
  <input type="submit" name="submit" id="submit" value="<?php echo _("Restore")?>">
</form>
