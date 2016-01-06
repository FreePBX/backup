<?php
$disabled = (isset($readonly) && !empty($readonly))?' disabled ':'';
?>
<h2><?php echo _("Email Server")?></h2>
<form class="fpbx-submit" action="" method="post" id="server_form" name="server_form">
	<input type="hidden" name="action" value="save">
	<input type="hidden" name="id" value="<?php echo isset($id)?$id:''?>">
	<input type="hidden" name="server_type" value="email">
<!--Server Name-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="name"><?php echo _("Server Name") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="name"></i>
					</div>
					<div class="col-md-9">
						<input type="text" class="form-control" id="name" name="name" value="<?php echo isset($name)?$name:''?>"<?php echo $disabled?>>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="name-help" class="help-block fpbx-help-block"><?php echo _("Name this Server")?></span>
		</div>
	</div>
</div>
<!--END-->
<!--Description-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="desc"><?php echo _("Description") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="desc"></i>
					</div>
					<div class="col-md-9">
						<input type="text" class="form-control" id="desc" name="desc" value="<?php echo isset($desc)?$desc:''?>"<?php echo $disabled?>>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="desc-help" class="help-block fpbx-help-block"><?php echo _("Description or notes for this server")?></span>
		</div>
	</div>
</div>
<!--END Description-->
<!--Email Address-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="addr"><?php echo _("Email Address") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="addr"></i>
					</div>
					<div class="col-md-9">
						<input type="email" class="form-control" id="addr" name="addr" value="<?php echo isset($addr)?$addr:''?>"<?php echo $disabled?>>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="addr-help" class="help-block fpbx-help-block"><?php echo _("Email address where backups should be emailed to")?></span>
		</div>
	</div>
</div>
<!--END Email Address-->
<!--Max Email Size-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="maxsize"><?php echo _("Max Email Size") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="maxsize"></i>
					</div>
					<div class="col-md-9">
						<?php
						$maxsize	= explode(' ', bytes2string($maxsize));
						$maxtype = isset($maxsize[1])?$maxsize[1]:'mb';
						?>
						<input type="number" class="form-control" id="maxsize" name="maxsize" value="<?php echo isset($maxsize[0])?$maxsize[0]:'10'?>"<?php echo $disabled?>>
						<div class="radioset">
							<input type="radio" name="maxtype" id="maxtypeb" value="b" <?php echo $maxtype =='b'?'CHECKED':''?><?php echo $disabled?>>
							<label for="maxtypeb"><?php echo _("B")?></label>
							<input type="radio" name="maxtype" id="maxtypekb" value="kb" <?php echo $maxtype =='kb'?'CHECKED':''?><?php echo $disabled?>>
							<label for="maxtypekb"><?php echo _("KB")?></label>
							<input type="radio" name="maxtype" id="maxtypemb" value="mb" <?php echo $maxtype =='mb'?'CHECKED':''?><?php echo $disabled?>>
							<label for="maxtypemb"><?php echo _("MB")?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="maxsize-help" class="help-block fpbx-help-block"><?php 
echo _('The maximum size a backup can be and still be emailed. '
	. 'Some email servers limit the size of email attachments, '
	. 'this will make sure that files larger than the max size '
	. 'are not sent.');
echo "<br>\n";
echo _('This has a maximum size of 25MB.'); 
?>
			</span>
		</div>
	</div>
</div>
<!--END Max Email Size-->
</form>
<script type="text/javascript">
  var immortal = <?php echo (isset($immortal) &&  !empty($immortal))?'true':'false';?>;
</script>
