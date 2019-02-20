<?php
$backupClass = FreePBX::Backup();
?>
<div class="container-fluid">
	<h1><?php echo _('Backup & Restore')?></h1>
	<div class = "display full-border">
		<div class="row">
			<div class="col-sm-12">
				<div class="fpbx-container">
					<ul class="nav nav-tabs" role="tablist">
						<li role="presentation" data-name="backup" class="active">
							<a href="#backup" aria-controls="backup" data-name="backup" role="tab" data-toggle="tab">
								<?php echo _("Backup")?>
							</a>
						</li>
						<li role="presentation" data-name="restore" class="change-tab">
							<a href="#restore" aria-controls="restore" data-name="restore" role="tab" data-toggle="tab">
								<?php echo _("Restore")?>
							</a>
						</li>
						<li role="presentation" data-name="settings" class="change-tab">
							<a href="#settings" aria-controls="settings" data-name="settings" role="tab" data-toggle="tab">
								<?php echo _("Global Settings")?>
							</a>
						</li>
					</ul>
					<div class="tab-content display">
						<div role="tabpanel" id="backup" class="tab-pane active">
							<?php echo $backupClass->showPage('backup'); ?>
						</div>
						<div role="tabpanel" id="restore" class="tab-pane">
							<?php echo $backupClass->showPage('restore'); ?>
						</div>
						<div role="tabpanel" id="settings" class="tab-pane">
							<?php echo $backupClass->showPage('settings'); ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>