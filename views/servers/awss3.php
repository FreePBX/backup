<?php
$html = '';
$html .= heading('Amazon S3 Server', 3) . '<hr class="backup-hr"/>';
$html .= form_hidden('server_type', 'awss3');
$html .= form_open($_SERVER['REQUEST_URI']);
$html .= form_hidden('action', 'save');
$html .= form_hidden('id', $id);


$table = new CI_Table;

//name
$label	= fpbx_label(_('Bucket Name'),_('Bucket or Path in Amazon S3 to store backups'));
$data 	= array(
			'name' => 'bucket', 
			'value' => $bucket
		);
$data = backup_server_writeable('bucket', $readonly, $data);
$table->add_row($label, form_input($data));

//decription
$label	= fpbx_label(_('Description'), _('Description or notes for this server'));
$data 	= array(
			'name' => 'desc', 
			'value' => $desc
		);
$data = backup_server_writeable('desc', $readonly, $data);
$table->add_row($label, form_input($data));

//hostname
$label = fpbx_label(_('AWS Access Key'), _('AWS Access Key (username)'));
$data  = array(
			'name' => 'awsaccesskey', 
			'value' => $awsaccesskey,
			'required' => ''
		);
$data = backup_server_writeable('awsaccesskey', $readonly, $data);
$table->add_row($label, form_input($data));
		
//port
$data = array(
			'name' => 'awssecret', 
			'value' => $awssecret,
			'required' => ''
		);
$data = backup_server_writeable('awssecret', $readonly, $data);
$table->add_row(fpbx_label(_('Secret'), _('AWS Secret (password)')), form_input($data));
		
$html .= $table->generate();

if ($readonly != array('*')) {
	$html .= form_submit('submit', _('Save'));
}

if ($immortal != 'true') {
	$html .= form_submit('submit', _('Delete'));
}
$html .= form_close();

echo $html;
?>
