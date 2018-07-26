<div class="section-title" data-for="backup-warmspare">
  <h3>
    <i class="fa fa-minus"></i>
    <?php echo _("Warm Spare") ?>
  </h3>
</div>
<div class="section" data-id="backup-warmspare">
  <!--Enable-->
  <div class="element-container">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspareenables">
            <?php echo _("Enable") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspareenables"></i>
        </div>
        <div class="col-md-9 radioset">
          <input type="radio" name="warmspareenables" id="warmspareenablesyes" value="yes" <?php echo ($warmspareenables=="yes" ?"CHECKED": "") ?>>
          <label for="warmspareenablesyes">
            <?php echo _("Yes");?>
          </label>
          <input type="radio" name="warmspareenables" id="warmspareenablesno" <?php echo ($warmspareenables=="yes" ? "": "CHECKED")?>>
          <label for="warmspareenablesno">
            <?php echo _("No");?>
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspareenables-help" class="help-block fpbx-help-block">
          <?php echo _("Should the warm spare feature be enabled")?>
        </span>
      </div>
    </div>
  </div>
  <!--END Enable-->
  <!--Enable Remote Trunks-->
  <div class="element-container warmspare">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remotetrunks">
            <?php echo _("Enable Remote Trunks") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remotetrunks"></i>
        </div>
        <div class="col-md-9 radioset">
          <input type="radio" name="warmspare_remotetrunks" id="warmspare_remotetrunksyes" value="yes" <?php echo ($warmspare_remotetrunks == "yes" ? "CHECKED" : "") ?>>
          <label for="warmspare_remotetrunksyes">
            <?php echo _("Yes"); ?>
          </label>
          <input type="radio" name="warmspare_remotetrunks" id="warmspare_remotetrunksno" <?php echo ($warmspare_remotetrunks == "yes" ? "" : "CHECKED")?>>
          <label for="warmspare_remotetrunksno">
            <?php echo _("No"); ?>
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remotetrunks-help" class="help-block fpbx-help-block">
          <?php echo _("Should the remote trunks be enabled") ?>
        </span>
      </div>
    </div>
  </div>
  <!--END Enable Remote Trunks-->
    <!--Exclude NAT settings-->
  <div class="element-container warmspare">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remotenat">
            <?php echo _("Exclude NAT settings") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remotenat"></i>
        </div>
        <div class="col-md-9 radioset">
          <input type="radio" name="warmspare_remotenat" id="warmspare_remotenatyes" value="yes" <?php echo ($warmspare_remotenat == "yes" ? "CHECKED" : "") ?>>
          <label for="warmspare_remotenatyes">
            <?php echo _("Yes"); ?>
          </label>
          <input type="radio" name="warmspare_remotenat" id="warmspare_remotenatno" <?php echo ($warmspare_remotenat == "yes" ? "" : "CHECKED")?>>
          <label for="warmspare_remotenatno">
            <?php echo _("No"); ?>
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remotenat-help" class="help-block fpbx-help-block">
          <?php echo _("Explicitly exclude any machine-specific IP settings. This allows you to have a warm-spare machine with a different IP address.") ?>
        </span>
      </div>
    </div>
  </div>
  <!--Exclude NAT settings-->
    <!--Exclude Bind Address-->
  <div class="element-container warmspare">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remotebind">
            <?php echo _("Exclude Bind Address") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remotebind"></i>
        </div>
        <div class="col-md-9 radioset">
          <input type="radio" name="warmspare_remotebind" id="warmspare_remotebindyes" value="yes" <?php echo ($warmspare_remotebind == "yes" ?
                                                                                                    "CHECKED" : "") ?>>
          <label for="warmspare_remotebindyes">
            <?php echo _("Yes"); ?>
          </label>
          <input type="radio" name="warmspare_remotebind" id="warmspare_remotebindno" <?php echo ($warmspare_remotebind == "yes" ? "" : "CHECKED")
                                                                                      ?>>
          <label for="warmspare_remotebindno">
            <?php echo _("No"); ?>
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remotebind-help" class="help-block fpbx-help-block">
          <?php echo _("Explicitly exclude any machine-specific Bindaddres. This allows you to have a warm-spare machine with a different IP address.") ?>
        </span>
      </div>
    </div>
  </div>
  <!--Exclude Bind Address-->
    <!--Exclude DNS-->
  <div class="element-container warmspare">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remotedns">
            <?php echo _("Exclude DNS") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remotedns"></i>
        </div>
        <div class="col-md-9 radioset">
          <input type="radio" name="warmspare_remotedns" id="warmspare_remotednsyes" value="yes" <?php echo ($warmspare_remotedns == "yes" ? "CHECKED" : "") ?>>
          <label for="warmspare_remotednsyes">
            <?php echo _("Yes"); ?>
          </label>
          <input type="radio" name="warmspare_remotedns" id="warmspare_remotednsno" <?php echo ($warmspare_remotedns == "yes" ? "" : "CHECKED")?>>
          <label for="warmspare_remotednsno">
            <?php echo _("No"); ?>
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remotedns-help" class="help-block fpbx-help-block">
          <?php echo _("Explicitly exclude any machine-specific DNS. This allows you to have a warm-spare machine with a different DNS.") ?>
        </span>
      </div>
    </div>
  </div>
  <!--Exclude DNS-->
    <!--Exclude DNS-->
  <div class="element-container warmspare">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remoteapply">
            <?php echo _("Apply Configs") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remoteapply"></i>
        </div>
        <div class="col-md-9 radioset">
          <input type="radio" name="warmspare_remoteapply" id="warmspare_remoteapplyyes" value="yes" <?php echo ($warmspare_remoteapply == "yes" ?"CHECKED" : "") ?>>
          <label for="warmspare_remoteapplyyes">
            <?php echo _("Yes"); ?>
          </label>
          <input type="radio" name="warmspare_remoteapply" id="warmspare_remoteapplyno" <?php echo ($warmspare_remoteapply == "yes" ? "" : "CHECKED")?>>
          <label for="warmspare_remoteapplyno">
            <?php echo _("No"); ?>
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remoteapply-help" class="help-block fpbx-help-block">
          <?php echo _("Equivalence of clicking the red button, will happen automatically after a restore on a Standby system") ?>
        </span>
      </div>
    </div>
  </div>
  <!--Apply Configs-->
  <!--Remote IP-->
  <div class="element-container warmspare">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_remoteip">
            <?php echo _("Remote IP") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_remoteip"></i>
        </div>
        <div class="col-md-9">
          <input type="text" class="form-control" id="warmspare_remote" name="warmspare_remote" value="<?php echo isset($warmspare_remoteip)?$warmspare_remoteip:''?>">
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_remoteip-help" class="help-block fpbx-help-block">
          <?php echo _("The address of the remote server.")?>
        </span>
      </div>
    </div>
  </div>
  <!--END Remote IP-->
  <!--Remote User-->
  <div class="element-container warmspare">
    <div class="row">
      <div class="form-group">
        <div class="col-md-3">
          <label class="control-label" for="warmspare_user">
            <?php echo _("Remote User") ?>
          </label>
          <i class="fa fa-question-circle fpbx-help-icon" data-for="warmspare_user"></i>
        </div>
        <div class="col-md-9">
          <input type="text" class="form-control" id="warmspare_user" name="warmspare_user" value="<?php echo isset($warmspare_user) ? $warmspare_user : '' ?>">
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <span id="warmspare_user-help" class="help-block fpbx-help-block">
          <?php echo _("User on remote system with proper permissions, usually root.")?>
        </span>
      </div>
    </div>
  </div>
  <!--Remote User-->
</div>