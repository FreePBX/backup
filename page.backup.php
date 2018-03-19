<?php
$backupClass = FreePBX::Backup();
?>
<div class="container-fluid">
	<div class = "display full-border">
		<div class="row">
			<div class="col-sm-12">
				<div class="fpbx-container">
					<div class="display full-border">
						<ul class="nav nav-tabs">
							<li role="presentation" class='<?php echo ((isset($_GET['view']) && $_GET['view'] == 'form') || !isset($_GET['view']))?"active":""?>'><a href="?display=backup"><?php echo _("Backup")?></a></li>
							<li role="presentation"><a href="?display=backup_restore"><?php echo _("Restore")?></a></li>
							<li role="presentation" class='<?php echo (isset($_GET['view']) && $_GET['view'] == 'download')?"active":""?>'><a href="?display=backup&view=download"><?php echo _("Download")?></a></li>
							<!--Hide for alpha/beta... -->
							<li role="presentation" class='<?php echo (isset($_GET['view']) && $_GET['view'] == 'transfer')?"active":""?>'><a href="?display=backup&view=transfer"><?php echo _("System Transfer")?></a></li>
						</ul>
						<br/>
						<?php echo $backupClass->showPage('backup'); ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
