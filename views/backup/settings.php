<!--Public Key-->
<div class="element-container">
	<div class="row">
		<div class="form-group">
			<div class="col-md-3">
				<label class="control-label" for="publickey"><?php echo _("Public Key") ?></label>
				<i class="fa fa-question-circle fpbx-help-icon" data-for="publickey"></i>
			</div>
			<div class="col-md-9">
				<textarea disabled id="publickey" class="form-control" rows='8'><?php echo $publickey?></textarea>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="publickey-help" class="help-block fpbx-help-block"><?php echo _("Public SSH key to allow other servers to connect")?></span>
		</div>
	</div>
</div>
<!--Runtime Log Path-->
