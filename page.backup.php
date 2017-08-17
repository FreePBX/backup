<?php
$backupClass = FreePBX::Backup();
?>
<div class="container-fluid">
	<h1><?php echo _('Backup')?></h1>
	<div class = "display full-border">
		<div class="row">
			<div class="col-sm-12">
				<div class="fpbx-container">
					<div class="display full-border">
						<?php echo $backupClass->showPage('backup'); ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
