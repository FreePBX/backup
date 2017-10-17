<div class="section-title" data-for="backup-warmspare"><h3><i class="fa fa-minus"></i> <?php echo _("Warm Spare") ?></h3></div>
<div class="section" data-id="backup-warmspare">
  <!--Enable-->
  <div class="element-container">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspareenables"><?php echo _("Enable") ?></label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspareenables"></i>
        </div>
        <div class="col-md-9 radioset">
              <input type="radio" name="warmspareenables" id="warmspareenablesyes" value="yes" <?php echo ($warmspareenables == "yes"?"CHECKED":"") ?>>
          <label for="warmspareenablesyes"><?php echo _("Yes");?></label>
            <input type="radio" name="warmspareenables" id="warmspareenablesno" <?php echo ($warmspareenables == "yes"?"":"CHECKED") ?>>
          <label for="warmspareenablesno"><?php echo _("No");?></label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspareenables-help" class="help-block fpbx-help-block"><?php echo _("Should the warm spare feature be enabled")?></span>
      </div>
    </div>
  </div>
  <!--END Enable-->
  <!--Server Type-->
  <div class="element-container">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_type"><?php echo _("Server Type") ?></label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_type"></i>
        </div>
        <div class="col-md-9 radioset">
          <input type="radio" name="warmspare_type" id="warmspare_typeprimary" value="primary" <?php echo ($warmspare_type == "primary"?"CHECKED":"") ?>>
          <label for="warmspare_typeprimary"><?php echo _("Primary");?></label>
          <input type="radio" name="warmspare_type" id="warmspare_typespare" value="spare" <?php echo ($warmspare_type == "spare"?"CHECKED":"") ?>>
          <label for="warmspare_typespare"><?php echo _("Spare");?></label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_type-help" class="help-block fpbx-help-block"><?php echo _("Is this the server that will be sending data (Primary) or receiving data (Spare)")?></span>
      </div>
    </div>
  </div>
  <!--END Server Type-->
  <!--Enable Remote Trunks-->
  <div class="element-container">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remote"><?php echo _("Enable Remote Trunks") ?></label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remote"></i>
        </div>
        <div class="col-md-9 radioset">
          <input type="radio" name="warmspare_remote" id="warmspare_remoteyes" value="yes" <?php echo ($warmspare_remote == "yes"?"CHECKED":"") ?>>
          <label for="warmspare_remoteyes"><?php echo _("Yes");?></label>
          <input type="radio" name="warmspare_remote" id="warmspare_remoteno" <?php echo ($warmspare_remote == "yes"?"":"CHECKED") ?>>
          <label for="warmspare_remoteno"><?php echo _("No");?></label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remote-help" class="help-block fpbx-help-block"><?php echo _("Should the remote trunks be enabled")?></span>
      </div>
    </div>
  </div>
  <!--END Enable Remote Trunks-->
  <!--Remote IP-->
  <div class="element-container">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remote"><?php echo _("Remote IP") ?></label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remote"></i>
        </div>
        <div class="col-md-9">
          <input type="text" class="form-control" id="warmspare_remote" name="warmspare_remote" value="<?php echo isset($warmspare_remot)?$warmspare_remot:''?>">
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remote-help" class="help-block fpbx-help-block"><?php echo _("The address of the remote server.")?></span>
      </div>
    </div>
  </div>
  <!--END Remote IP-->
</div>
