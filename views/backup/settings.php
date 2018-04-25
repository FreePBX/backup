<form class="fpbx-submit" name="settings" action="config.php?display=backup" method="post" role="form">
    <!--From Email-->
    <div class="element-container">
        <div class="row">
            <div class="form-group">
                <div class="col-md-3">
                    <label class="control-label" for="fromemail"><?php echo _("From Email") ?></label>
                    <i class="fa fa-question-circle fpbx-help-icon" data-for="fromemail"></i>
                </div>
                <div class="col-md-9">
                    <input type="text" class="form-control" id="fromemail" name="fromemail" value="<?php echo $fromemail?>">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <span id="fromemail-help" class="help-block fpbx-help-block"><?php echo _("Email address emails will come from")?></span>
            </div>
        </div>
    </div>
    <!--From Email-->
    <!--Runtime Log Path-->
    <div class="element-container">
        <div class="row">
            <div class="form-group">
                <div class="col-md-3">
                    <label class="control-label" for="logpath"><?php echo _("Runtime Log Path") ?></label>
                    <i class="fa fa-question-circle fpbx-help-icon" data-for="logpath"></i>
                </div>
                <div class="col-md-9">
                    <input type="text" class="form-control" id="logpath" name="logpath" value="<?php echo $logpath?>">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <span id="logpath-help" class="help-block fpbx-help-block"><?php echo _("Location of the log kept during backup")?></span>
            </div>
        </div>
    </div>
    <!--Runtime Log Path-->
</form>