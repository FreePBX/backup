
<table class="table table-striped" id="template_table">
	<tr>
		<th>
			<?php echo _('Type')?>
		</th>
		<th>
			<?php echo _('Path/DB')?>
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
		$d = backup_template_generate_tr($c, $i, $immortal);
		echo '<tr><td>'.$d['type'].'</td><td>'.$d['path'].'</td><td>'.$d['exclude'].'</td><td>'.$d['delete'].'</td></tr>';
	}
	?>
</table>
<?php
$html = '';


if ($immortal != 'true') {
	$html .= '<i class="fa fa-plus" style="cursor:pointer" title="Add Entry" id="add_entry"></i>';
}

//include javascript variables for add button
$html	.= '<script type="text/javascript">';
$file	= backup_template_generate_tr('TR_UID', array('type' => 'file', 'path' => '', 'exclude' => array()), '', true);
$dir	= backup_template_generate_tr('TR_UID', array('type' => 'dir', 'path' => '', 'exclude' => array()), '', true);
$mysql	= backup_template_generate_tr('TR_UID', array('type' => 'mysql', 'path' => '', 'exclude' => array()), '', true);
$astdb	= backup_template_generate_tr('TR_UID', array('type' => 'astdb', 'path' => '', 'exclude' => array()), '', true);

$html	.= 'template_tr = new Array();';
$html	.= 'template_tr["file"] = '		. json_encode($file)	. PHP_EOL;
$html	.= 'template_tr["dir"] = '		. json_encode($dir)		. PHP_EOL;
$html	.= 'template_tr["mysql"] = '	. json_encode($mysql)	. PHP_EOL;
$html	.= 'template_tr["astdb"] = '	. json_encode($astdb)	. PHP_EOL;
$html	.= '</script>'. PHP_EOL;
$data 	= array(
			''		=> '== ' . _('chose') . ' ==',
			'file'	=> 'File',
			'dir'	=> 'Directory',
			'mysql'	=> 'Mysql',
			'astdb'	=> 'Asterisk Database',
			);
$html	.= form_dropdown('add_tr_select', $data, '', 'style="display:none"');

$html .= '<script type="text/javascript" src="modules/backup/assets/js/views/templates.js"></script>';
echo $html;
