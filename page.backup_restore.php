<?php
$backupClass = FreePBX::Backup();
?>


<div class="container-fluid">
	<h1><?php echo _('Restore')?></h1>
	<div class="row">
		<div class="col-sm-12">
			<div class="fpbx-container">
				<div class="display no-border">
					<ul class="nav nav-tabs">
						<li role="presentation"><a href="?display=backup"><?php echo _("Backup")?></a></li>
						<li role="presentation" class='active'><a href="?display=backup_restore"><?php echo _("Restore")?></a></li>
						<li role="presentation"><a href="?display=backup&view=download"><?php echo _("Download")?></a></li>
					</ul>
					<div class="display">
						<?php echo $backupClass->showPage('restore'); ?>
					</div>
			</div>
		</div>
	</div>
</div>
