<h1> <?php echo _("Running Backup") ?> </h1>
<h3> <?php echo _("Backup Log") ?> </h3>
<hr/>
<span id="logtext"><?php echo _("Backup starting no received yet data.") ?></span>
<br/>
<input type="hidden" id="id" value = "<?php echo $id ?>">
<a href="?display=backup" class="btn btn-default"><?php echo _("Backup List") ?></a>
<script>
    $(document).ready(function () {
    $.ajax({
			url: ajaxurl,
			data: {
				module: 'backup',
				command: 'run',
				id: $("#id").val()
			},
		})
		.then(data => {
			if (data.status) {
				lockButtons(data.backupid, data.transaction);
			}
		});
});

</script>