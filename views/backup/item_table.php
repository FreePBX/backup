<?php $backupobj = \FreePBX::Backup(); ?>
<table class="table table-striped" id="template_table">
	<tr>
		<th>
			<?php echo _('Type')?>
		</th>
		<th>
			<?php echo _('Complete Path')?>
		</th>
		<th>
			<?php echo _('Exclude')?>
		</th>
		<th>
			<?php echo _('Delete')?>
		</th>
	</tr>
	<?php
	$c = 0;
	foreach ($items as $i){
		$c++;
		$d = $backupobj->backup_template_generate_tr($c, $i);
		echo '<tr><td>'.$d['type'].'</td><td>'.$d['path'].'</td><td>'.$d['exclude'].'</td><td>'.$d['delete'].'</td></tr>';
	}
	?>
</table>
<?php
$html = '';
$html .= '<i class="fa fa-plus" style="cursor:pointer" title="Add Entry" id="add_entry"></i>';

//include javascript variables for add button
$html	.= '<script type="text/javascript">';
$file	= $backupobj->backup_template_generate_tr('TR_UID', ['type' => 'file', 'path' => '', 'exclude' => []], true);
$dir	= $backupobj->backup_template_generate_tr('TR_UID', ['type' => 'dir', 'path' => '', 'exclude' => []], true);

$html	.= 'template_tr = new Array();';
$html	.= 'template_tr["file"] = '	. json_encode($file, JSON_THROW_ON_ERROR) . PHP_EOL;
$html	.= 'template_tr["dir"] = ' . json_encode($dir, JSON_THROW_ON_ERROR) . PHP_EOL;
$html	.= '</script>'. PHP_EOL;
$data 	= ['' => '== ' . _('choose') . ' ==', 'file' => 'File', 'dir' => 'Directory'];
$html	.= form_dropdown('add_tr_select', $data, '', 'style="display:none"');
echo $html;
