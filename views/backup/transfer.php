<!--Transfer Id-->
<div class="element-container">
  <div class="row">
    <div class="form-group">
      <div class="col-md-3">
        <label class="control-label" for="transferId"><?php echo _("Transfer Id") ?></label>
        <i class="fa fa-question-circle fpbx-help-icon" data-for="transferId"></i>
      </div>
      <div class="col-md-9">
        <input type="text" class="form-control" id="transferId" name="transferId" value="<?php echo isset($transferI)?$transferI:''?>">
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-md-12">
      <span id="transferId-help" class="help-block fpbx-help-block"><?php echo _("ID From the donor machine")?></span>
    </div>
  </div>
</div>
<!--END Transfer Id-->
<!--Server Mode-->
<div class="element-container">
  <div class="row">
    <div class="form-group">
      <div class="col-md-3">
        <label class="control-label" for="serverMode"><?php echo _("Server Mode") ?></label>
        <i class="fa fa-question-circle fpbx-help-icon" data-for="serverMode"></i>
      </div>
      <div class="col-md-9 radioset">
        <input type="radio" name="serverMode" id="serverModeyes" value="donor" <?php echo ($serverMode == "donor"?"CHECKED":"") ?>>
        <label for="serverModeyes"><?php echo _("Donor");?></label>
        <input type="radio" name="serverMode" id="serverModeno" value="recipient" <?php echo ($serverMode == "donor"?"":"CHECKED") ?>>
        <label for="serverModeno"><?php echo _("Recipient");?></label>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-md-12">
      <span id="serverMode-help" class="help-block fpbx-help-block"><?php echo _("Is this the donor machine or the recipient")?></span>
    </div>
  </div>
</div>
<!--END Server Mode-->
