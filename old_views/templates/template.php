<?php
if (!isset($id)) {
	$id = "";
}
?>

<h2><?php echo _("Backup Template")?></h2>
<form class="fpbx-submit" name="backup_template" id="backup_template" action="?display=backup_templates" method="post" data-fpbx-delete="?display=backup_templates&action=delete&id=<?php echo $id; ?>">
	<input type="hidden" name="action" value="save">
	<input type="hidden" name="id" value="<?php echo $id; ?>">
	<!--Template Name-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="name"><?php echo _("Template Name") ?></label>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="name" name="name" value="<?php echo isset($name)?$name:''?>">
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<!--END Template Name-->
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
							<input type="text" class="form-control" id="desc" name="desc" value="<?php echo isset($desc)?$desc:''?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="desc-help" class="help-block fpbx-help-block"><?php echo _("Optional description or notes for this backup temnplate.")?></span>
			</div>
		</div>
	</div>
	<!--END Description-->
	<br/>
	<?php echo load_view(dirname(__FILE__) . '/../item_table.php', array('items' => $items, 'immortal' => $immortal));?>
</form>
